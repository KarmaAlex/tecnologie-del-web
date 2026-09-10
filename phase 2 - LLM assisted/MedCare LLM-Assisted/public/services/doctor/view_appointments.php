<?php
/**
 * public/services/doctor/view_appointments.php
 *
 * Service registrato come 'doctor_view_appointments'.
 * Sola lettura: elenca tutti gli appuntamenti (slot con status 'booked'
 * o 'cancelled') del medico loggato, più recenti in cima. Diverso da
 * view_patient_history.php: qui la vista è "i miei appuntamenti nel
 * tempo", non "lo storico clinico di un paziente specifico".
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';
require_once __DIR__ . '/../../../include/badges.php';

requireService('doctor_view_appointments');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$doctorStmt = $pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id = :user_id LIMIT 1');
$doctorStmt->execute([':user_id' => $userId]);
$doctorId = $doctorStmt->fetchColumn();

$errorHtml = '';
$rows = [];
$scope = ($_GET['scope'] ?? 'upcoming') === 'all' ? 'all' : 'upcoming';

if ($doctorId === false) {
    $errorHtml = '<p class="feedback feedback-error">Il tuo profilo medico non è ancora stato completato. Contatta l\'amministrazione.</p>';
} else {
    $sql = "SELECT sl.slot_id, sl.slot_date, sl.slot_time, sl.status,
                   p.patient_id, u.first_name, u.last_name
            FROM appointment_slots sl
            JOIN patients p ON p.patient_id = sl.patient_id
            JOIN users u ON u.user_id = p.user_id
            WHERE sl.doctor_id = :doctor_id AND sl.patient_id IS NOT NULL";

    if ($scope === 'upcoming') {
        $sql .= ' AND sl.slot_date >= CURDATE()';
    }

    $sql .= ' ORDER BY sl.slot_date DESC, sl.slot_time DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':doctor_id' => (int)$doctorId]);
    $rows = $stmt->fetchAll();
}

$appointmentRows = '';
if (empty($rows)) {
    $appointmentRows = '<tr><td colspan="4"><em>Nessun appuntamento trovato.</em></td></tr>';
} else {
    foreach ($rows as $row) {
        $appointmentRows .= '<tr>'
            . '<td>' . htmlspecialchars($row['slot_date'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars(substr($row['slot_time'], 0, 5), ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES)
            . ' — <a href="/services/doctor/view_patient_history.php?patient_id=' . (int)$row['patient_id'] . '">storico</a></td>'
            . '<td>' . renderSlotBadge($row['status']) . '</td>'
            . '</tr>';
    }
}

$scopeUpcoming = $scope === 'upcoming';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/doctor/view_appointments');
$tpl->setContent('error_html', $errorHtml);
$tpl->setContent('appointment_rows', $appointmentRows);
$tpl->setContent('scope_upcoming_selected', $scopeUpcoming ? ' selected' : '');
$tpl->setContent('scope_all_selected', $scopeUpcoming ? '' : ' selected');

renderHeader('I miei appuntamenti');
echo $tpl->get();
renderFooter();
