<?php
/**
 * public/services/doctor/manage_slots.php
 *
 * Service registrato come 'doctor_manage_slots'.
 * Il medico loggato apre nuovi slot prenotabili (status 'open') e può
 * annullare slot propri non ancora prenotati. Diverso da
 * patient/book_appointment.php (il paziente prenota slot esistenti) e
 * da doctor/update_shift.php (gestisce l'orario di lavoro generale, non
 * i singoli slot visita).
 *
 * Uno slot già 'booked' non può essere annullato da qui: l'annullamento
 * di un appuntamento già preso da un paziente è un'azione diversa
 * (fuori scope di questo service) che richiederebbe di avvisare il
 * paziente — qui si gestiscono solo slot ancora liberi.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';
require_once __DIR__ . '/../../../include/badges.php';

requireService('doctor_manage_slots');

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

// -----------------------------------------------------------------
// POST: apertura o annullamento slot
// -----------------------------------------------------------------
if ($doctorId !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $slotDate = $_POST['slot_date'] ?? '';
            $slotTime = $_POST['slot_time'] ?? '';

            $dateOk = (bool)DateTime::createFromFormat('Y-m-d', $slotDate);
            $timeOk = (bool)DateTime::createFromFormat('H:i', $slotTime);

            if (!$dateOk || !$timeOk) {
                $feedback = 'Compila data e ora con valori validi.';
                $feedbackType = 'error';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO appointment_slots (doctor_id, slot_date, slot_time, status, patient_id)
                     VALUES (:doctor_id, :slot_date, :slot_time, 'open', NULL)"
                );
                $stmt->execute([
                    ':doctor_id' => (int)$doctorId,
                    ':slot_date' => $slotDate,
                    ':slot_time' => $slotTime,
                ]);
                $feedback = 'Slot aperto con successo.';
                $feedbackType = 'success';
            }
        } elseif ($action === 'cancel') {
            $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);

            if (!$slotId) {
                $feedback = 'Slot non valido.';
                $feedbackType = 'error';
            } else {
                // Verifica di dominio: lo slot appartiene a questo
                // medico ed è ancora 'open'? Uno slot già prenotato da
                // un paziente non può essere annullato da qui (vedi
                // docblock in testa al file).
                $ownStmt = $pdo->prepare(
                    "SELECT status FROM appointment_slots WHERE slot_id = :slot_id AND doctor_id = :doctor_id"
                );
                $ownStmt->execute([':slot_id' => $slotId, ':doctor_id' => (int)$doctorId]);
                $currentStatus = $ownStmt->fetchColumn();

                if ($currentStatus === false) {
                    logAudit($userId, 'doctor_manage_slots', 'ACCESS_DENIED');
                    denyAccess('Questo slot non appartiene al tuo profilo.');
                } elseif ($currentStatus !== 'open') {
                    $feedback = 'Solo gli slot ancora liberi possono essere annullati da qui.';
                    $feedbackType = 'error';
                } else {
                    $updStmt = $pdo->prepare(
                        "UPDATE appointment_slots SET status = 'cancelled' WHERE slot_id = :slot_id"
                    );
                    $updStmt->execute([':slot_id' => $slotId]);
                    $feedback = 'Slot annullato.';
                    $feedbackType = 'success';
                }
            }
        } else {
            $feedback = 'Azione non riconosciuta.';
            $feedbackType = 'error';
        }

        logAudit($userId, 'doctor_manage_slots', 'ACCESS_GRANTED:' . $action);

    } catch (Throwable $e) {
        error_log('[manage_slots] ' . $e->getMessage());
        $feedback = 'Si è verificato un errore. Riprova.';
        $feedbackType = 'error';
    }
}

// -----------------------------------------------------------------
// GET: elenco dei propri slot futuri (open, booked, cancelled)
// -----------------------------------------------------------------
$slotRows = '<tr><td colspan="4"><em>Nessuno slot programmato.</em></td></tr>';

if ($doctorId !== false) {
    $stmt = $pdo->prepare(
        "SELECT sl.slot_id, sl.slot_date, sl.slot_time, sl.status,
                u.first_name, u.last_name
         FROM appointment_slots sl
         LEFT JOIN patients p ON p.patient_id = sl.patient_id
         LEFT JOIN users u ON u.user_id = p.user_id
         WHERE sl.doctor_id = :doctor_id AND sl.slot_date >= CURDATE()
         ORDER BY sl.slot_date, sl.slot_time"
    );
    $stmt->execute([':doctor_id' => (int)$doctorId]);
    $slots = $stmt->fetchAll();

    if (!empty($slots)) {
        $slotRows = '';
        foreach ($slots as $slot) {
            $patientLabel = $slot['first_name']
                ? htmlspecialchars($slot['first_name'] . ' ' . $slot['last_name'], ENT_QUOTES)
                : '<span class="muted">—</span>';

            $actionCell = '<span class="muted">—</span>';
            if ($slot['status'] === 'open') {
                $actionCell = '<form method="post" action="/services/doctor/manage_slots.php" class="inline-form" data-confirm="Confermi l\'annullamento dello slot?">'
                    . csrfField()
                    . '<input type="hidden" name="action" value="cancel">'
                    . '<input type="hidden" name="slot_id" value="' . (int)$slot['slot_id'] . '">'
                    . '<button type="submit" class="danger">Annulla</button>'
                    . '</form>';
            }

            $slotRows .= '<tr>'
                . '<td>' . htmlspecialchars($slot['slot_date'], ENT_QUOTES) . ' '
                . htmlspecialchars(substr($slot['slot_time'], 0, 5), ENT_QUOTES) . '</td>'
                . '<td>' . renderSlotBadge($slot['status']) . '</td>'
                . '<td>' . $patientLabel . '</td>'
                . '<td>' . $actionCell . '</td>'
                . '</tr>';
        }
    }
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/doctor/manage_slots');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('slot_rows', $slotRows);
$tpl->setContent('csrf_field', csrfField());

renderHeader('Gestione slot');
echo $tpl->get();
renderFooter();
