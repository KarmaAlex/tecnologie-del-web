<?php
/**
 * public/services/patient/view_prescriptions.php
 *
 * Service registrato come 'patient_view_prescriptions'. Sola lettura:
 * elenca le prescrizioni del paziente loggato, più recenti in cima.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('patient_view_prescriptions');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$patientStmt = $pdo->prepare('SELECT patient_id FROM patients WHERE user_id = :user_id LIMIT 1');
$patientStmt->execute([':user_id' => $userId]);
$patientId = $patientStmt->fetchColumn();

$rows = [];
if ($patientId !== false) {
    $stmt = $pdo->prepare(
        "SELECT p.issued_at, p.medication, p.dosage, p.instructions,
                d.first_name, d.last_name
         FROM prescriptions p
         JOIN doctors doc ON doc.doctor_id = p.doctor_id
         JOIN users d ON d.user_id = doc.user_id
         WHERE p.patient_id = :patient_id
         ORDER BY p.issued_at DESC"
    );
    $stmt->execute([':patient_id' => (int)$patientId]);
    $rows = $stmt->fetchAll();
}

$prescriptionRows = '';
if ($patientId === false) {
    $prescriptionRows = '<tr><td colspan="5"><em>Profilo paziente non ancora collegato al tuo account.</em></td></tr>';
} elseif (empty($rows)) {
    $prescriptionRows = '<tr><td colspan="5"><em>Nessuna prescrizione registrata.</em></td></tr>';
} else {
    foreach ($rows as $row) {
        $instructions = $row['instructions'] !== null && $row['instructions'] !== ''
            ? htmlspecialchars($row['instructions'], ENT_QUOTES)
            : '<span class="muted">—</span>';

        $prescriptionRows .= '<tr>'
            . '<td>' . htmlspecialchars(date('d/m/Y', strtotime($row['issued_at'])), ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($row['medication'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($row['dosage'], ENT_QUOTES) . '</td>'
            . '<td>' . $instructions . '</td>'
            . '<td>Dr. ' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES) . '</td>'
            . '</tr>';
    }
}

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/patient/view_prescriptions');
$tpl->setContent('prescription_rows', $prescriptionRows);

renderHeader('Le mie prescrizioni');
echo $tpl->get();
renderFooter();
