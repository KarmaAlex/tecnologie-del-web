<?php
/**
 * public/services/doctor/view_patient_history.php
 *
 * Service registrato come 'doctor_view_patient_history'.
 *
 * requireService() verifica SOLO l'appartenenza al gruppo Medical_Staff.
 * Non basta: un medico non deve poter leggere lo storico di un paziente
 * con cui non ha mai avuto un rapporto clinico (nessun log, nessuno slot
 * prenotato). Questo secondo controllo va fatto qui, a livello di query,
 * perché è specifico del dominio (relazione medico-paziente), non del
 * gatekeeper generico RBAC.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('doctor_view_patient_history');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$doctorStmt = $pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id = :user_id LIMIT 1');
$doctorStmt->execute([':user_id' => $userId]);
$doctorId = $doctorStmt->fetchColumn();

$patientId = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

$patientInfo = null;
$logs = [];
$prescriptions = [];
$errorMessage = null;

if ($doctorId === false) {
    $errorMessage = 'Il tuo profilo medico non è ancora stato completato. Contatta l\'amministrazione.';
} elseif (!$patientId) {
    // Nessun paziente selezionato: mostriamo solo l'elenco dei pazienti
    // con cui questo medico ha una relazione clinica (per la select).
} else {
    // Verifica di dominio: il medico ha davvero una relazione clinica con
    // questo paziente? (almeno un log clinico oppure uno slot prenotato)
    $relationStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM (
            SELECT patient_id FROM medical_logs WHERE doctor_id = :doctor_id_1 AND patient_id = :patient_id_1
            UNION ALL
            SELECT patient_id FROM appointment_slots WHERE doctor_id = :doctor_id_2 AND patient_id = :patient_id_2
         ) AS relation"
    );
    $relationStmt->execute([
        ':doctor_id_1'  => (int)$doctorId,
        ':patient_id_1' => $patientId,
        ':doctor_id_2'  => (int)$doctorId,
        ':patient_id_2' => $patientId,
    ]);
    $hasRelation = (int)$relationStmt->fetchColumn() > 0;

    if (!$hasRelation) {
        logAudit($userId, 'doctor_view_patient_history', 'ACCESS_DENIED');
        denyAccess('Non hai una relazione clinica con questo paziente.');
    }

    $patientStmt = $pdo->prepare(
        'SELECT p.patient_id, u.first_name, u.last_name, p.date_of_birth, p.insurance_provider
         FROM patients p JOIN users u ON u.user_id = p.user_id
         WHERE p.patient_id = :patient_id LIMIT 1'
    );
    $patientStmt->execute([':patient_id' => $patientId]);
    $patientInfo = $patientStmt->fetch() ?: null;

    if ($patientInfo) {
        $logStmt = $pdo->prepare(
            'SELECT log_date, notes FROM medical_logs
             WHERE patient_id = :patient_id ORDER BY log_date DESC'
        );
        $logStmt->execute([':patient_id' => $patientId]);
        $logs = $logStmt->fetchAll();

        $prescStmt = $pdo->prepare(
            'SELECT issued_at, medication, dosage, instructions
             FROM prescriptions WHERE patient_id = :patient_id ORDER BY issued_at DESC'
        );
        $prescStmt->execute([':patient_id' => $patientId]);
        $prescriptions = $prescStmt->fetchAll();
    }
}

// Elenco pazienti con relazione clinica (per la select di ricerca)
$patientOptions = '<option value="">— Seleziona un paziente —</option>';
if ($doctorId !== false) {
    $listStmt = $pdo->prepare(
        "SELECT DISTINCT p.patient_id, u.first_name, u.last_name FROM patients p
         JOIN users u ON u.user_id = p.user_id
         WHERE p.patient_id IN (
             SELECT patient_id FROM medical_logs WHERE doctor_id = :doctor_id_1
             UNION
             SELECT patient_id FROM appointment_slots WHERE doctor_id = :doctor_id_2 AND patient_id IS NOT NULL
         )
         ORDER BY u.last_name, u.first_name"
    );
    $listStmt->execute([':doctor_id_1' => (int)$doctorId, ':doctor_id_2' => (int)$doctorId]);
    foreach ($listStmt->fetchAll() as $p) {
        $selected = ($patientId === (int)$p['patient_id']) ? ' selected' : '';
        $patientOptions .= '<option value="' . (int)$p['patient_id'] . '"' . $selected . '>'
            . htmlspecialchars($p['last_name'] . ' ' . $p['first_name'], ENT_QUOTES) . '</option>';
    }
}

// Rendering
$errorHtml = $errorMessage ? '<p class="feedback feedback-error">' . htmlspecialchars($errorMessage, ENT_QUOTES) . '</p>' : '';

$patientHeaderHtml = '';
$logRows = '<tr><td colspan="2"><em>Seleziona un paziente per vedere lo storico.</em></td></tr>';
$prescRows = '<tr><td colspan="4"><em>—</em></td></tr>';

if ($patientInfo) {
    $dob = $patientInfo['date_of_birth'] ? date('d/m/Y', strtotime($patientInfo['date_of_birth'])) : 'n/d';
    $patientHeaderHtml = '<h2>' . htmlspecialchars($patientInfo['first_name'] . ' ' . $patientInfo['last_name'], ENT_QUOTES) . '</h2>'
        . '<p>Data di nascita: ' . htmlspecialchars($dob, ENT_QUOTES) . ' — Assicurazione: '
        . htmlspecialchars($patientInfo['insurance_provider'] ?? 'n/d', ENT_QUOTES) . '</p>';

    $logRows = empty($logs)
        ? '<tr><td colspan="2"><em>Nessun referto registrato.</em></td></tr>'
        : '';
    foreach ($logs as $log) {
        $logRows .= '<tr><td>' . htmlspecialchars(date('d/m/Y H:i', strtotime($log['log_date'])), ENT_QUOTES) . '</td>'
            . '<td>' . nl2br(htmlspecialchars($log['notes'], ENT_QUOTES)) . '</td></tr>';
    }

    $prescRows = empty($prescriptions)
        ? '<tr><td colspan="4"><em>Nessuna prescrizione registrata.</em></td></tr>'
        : '';
    foreach ($prescriptions as $presc) {
        $instructions = $presc['instructions'] ? htmlspecialchars($presc['instructions'], ENT_QUOTES) : '—';
        $prescRows .= '<tr>'
            . '<td>' . htmlspecialchars(date('d/m/Y', strtotime($presc['issued_at'])), ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($presc['medication'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($presc['dosage'], ENT_QUOTES) . '</td>'
            . '<td>' . $instructions . '</td>'
            . '</tr>';
    }
}

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/doctor/view_patient_history');
$tpl->setContent('error_html', $errorHtml);
$tpl->setContent('patient_options', $patientOptions);
$tpl->setContent('patient_header_html', $patientHeaderHtml);
$tpl->setContent('log_rows', $logRows);
$tpl->setContent('prescription_rows', $prescRows);

renderHeader('Storico paziente');
echo $tpl->get();
renderFooter();
