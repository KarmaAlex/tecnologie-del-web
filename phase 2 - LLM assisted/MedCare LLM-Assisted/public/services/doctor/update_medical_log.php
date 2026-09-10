<?php
/**
 * public/services/doctor/update_medical_log.php
 *
 * Service registrato come 'doctor_log_medical_record'.
 * GET  -> form per selezionare un paziente (tra quelli con slot prenotati
 *         presso questo medico) e scrivere un nuovo referto.
 * POST -> inserisce la riga in medical_logs, opzionalmente collegata a
 *         uno slot specifico (slot_id).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('doctor_log_medical_record');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$doctorStmt = $pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id = :user_id LIMIT 1');
$doctorStmt->execute([':user_id' => $userId]);
$doctorId = $doctorStmt->fetchColumn();

$feedback = null;
$feedbackType = 'info';

if ($doctorId === false) {
    $feedback = 'Il tuo profilo medico non è ancora stato completato. Contatta l\'amministrazione.';
    $feedbackType = 'error';
}

/**
 * Verifica che il paziente indicato abbia una relazione clinica con
 * questo medico (almeno uno slot prenotato o un log precedente).
 * Stessa logica di dominio usata in view_patient_history.php.
 */
function doctorHasRelationWithPatient(PDO $pdo, int $doctorId, int $patientId): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM (
            SELECT patient_id FROM medical_logs WHERE doctor_id = :doctor_id_1 AND patient_id = :patient_id_1
            UNION ALL
            SELECT patient_id FROM appointment_slots WHERE doctor_id = :doctor_id_2 AND patient_id = :patient_id_2
         ) AS relation"
    );
    $stmt->execute([
        ':doctor_id_1'  => $doctorId,
        ':patient_id_1' => $patientId,
        ':doctor_id_2'  => $doctorId,
        ':patient_id_2' => $patientId,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

// -----------------------------------------------------------------
// POST: salvataggio referto
// -----------------------------------------------------------------
if ($doctorId !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $patientId = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT) ?: null;
    $notes = trim((string)($_POST['notes'] ?? ''));

    if (!$patientId || $notes === '') {
        $feedback = 'Seleziona un paziente e inserisci il testo del referto.';
        $feedbackType = 'error';
    } elseif (mb_strlen($notes) > 4000) {
        $feedback = 'Il referto è troppo lungo (massimo 4000 caratteri).';
        $feedbackType = 'error';
    } elseif (!doctorHasRelationWithPatient($pdo, (int)$doctorId, $patientId)) {
        logAudit($userId, 'doctor_log_medical_record', 'ACCESS_DENIED');
        denyAccess('Non hai una relazione clinica con questo paziente.');
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO medical_logs (patient_id, doctor_id, slot_id, log_date, notes)
                 VALUES (:patient_id, :doctor_id, :slot_id, NOW(), :notes)'
            );
            $stmt->execute([
                ':patient_id' => $patientId,
                ':doctor_id'  => (int)$doctorId,
                ':slot_id'    => $slotId,
                ':notes'      => $notes,
            ]);

            $feedback = 'Referto registrato con successo.';
            $feedbackType = 'success';
        } catch (Throwable $e) {
            error_log('[update_medical_log] ' . $e->getMessage());
            $feedback = 'Si è verificato un errore durante il salvataggio. Riprova.';
            $feedbackType = 'error';
        }
    }
}

// -----------------------------------------------------------------
// GET: elenco pazienti con relazione clinica, per la select del form
// -----------------------------------------------------------------
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
        $patientOptions .= '<option value="' . (int)$p['patient_id'] . '">'
            . htmlspecialchars($p['last_name'] . ' ' . $p['first_name'], ENT_QUOTES) . '</option>';
    }
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/doctor/update_medical_log');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('patient_options', $patientOptions);
$tpl->setContent('csrf_field', csrfField());

renderHeader('Nuovo referto clinico');
echo $tpl->get();
renderFooter();
