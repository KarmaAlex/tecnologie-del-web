<?php
/**
 * public/services/doctor/update_shift.php
 *
 * Service registrato come 'doctor_update_shift'.
 * Self-service: il medico loggato gestisce SOLO i propri turni futuri
 * (aggiunta/rimozione). Diverso da admin/manage_schedules.php, che
 * l'admin usa per vedere e modificare i turni di TUTTI i medici.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('doctor_update_shift');

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
// POST: aggiunta o rimozione turno
// -----------------------------------------------------------------
if ($doctorId !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $shiftDate = $_POST['shift_date'] ?? '';
            $startTime = $_POST['start_time'] ?? '';
            $endTime = $_POST['end_time'] ?? '';

            $dateOk = (bool)DateTime::createFromFormat('Y-m-d', $shiftDate);
            $startOk = (bool)DateTime::createFromFormat('H:i', $startTime);
            $endOk = (bool)DateTime::createFromFormat('H:i', $endTime);

            if (!$dateOk || !$startOk || !$endOk) {
                $feedback = 'Compila tutti i campi con valori validi.';
                $feedbackType = 'error';
            } elseif ($startTime >= $endTime) {
                $feedback = 'L\'orario di fine turno deve essere successivo a quello di inizio.';
                $feedbackType = 'error';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO doctor_shifts (doctor_id, shift_date, start_time, end_time)
                     VALUES (:doctor_id, :shift_date, :start_time, :end_time)'
                );
                $stmt->execute([
                    ':doctor_id'  => (int)$doctorId,
                    ':shift_date' => $shiftDate,
                    ':start_time' => $startTime,
                    ':end_time'   => $endTime,
                ]);
                $feedback = 'Turno aggiunto con successo.';
                $feedbackType = 'success';
            }
        } elseif ($action === 'delete') {
            $shiftId = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);

            if (!$shiftId) {
                $feedback = 'Turno non valido.';
                $feedbackType = 'error';
            } else {
                // Verifica di dominio: il turno appartiene davvero a
                // questo medico? Senza questo controllo un medico
                // potrebbe cancellare, indovinando l'id, il turno di un
                // collega — requireService() garantisce solo
                // l'appartenenza al gruppo Medical_Staff, non la
                // proprietà della singola riga.
                $ownStmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM doctor_shifts WHERE shift_id = :shift_id AND doctor_id = :doctor_id'
                );
                $ownStmt->execute([':shift_id' => $shiftId, ':doctor_id' => (int)$doctorId]);

                if ((int)$ownStmt->fetchColumn() === 0) {
                    logAudit($userId, 'doctor_update_shift', 'ACCESS_DENIED');
                    denyAccess('Questo turno non appartiene al tuo profilo.');
                }

                $delStmt = $pdo->prepare('DELETE FROM doctor_shifts WHERE shift_id = :shift_id');
                $delStmt->execute([':shift_id' => $shiftId]);
                $feedback = 'Turno rimosso.';
                $feedbackType = 'success';
            }
        } else {
            $feedback = 'Azione non riconosciuta.';
            $feedbackType = 'error';
        }

        logAudit($userId, 'doctor_update_shift', 'ACCESS_GRANTED:' . $action);

    } catch (Throwable $e) {
        error_log('[update_shift] ' . $e->getMessage());
        $feedback = 'Si è verificato un errore. Riprova.';
        $feedbackType = 'error';
    }
}

// -----------------------------------------------------------------
// GET: elenco dei propri turni futuri
// -----------------------------------------------------------------
$shiftRows = '<tr><td colspan="3"><em>Nessun turno programmato.</em></td></tr>';

if ($doctorId !== false) {
    $stmt = $pdo->prepare(
        'SELECT shift_id, shift_date, start_time, end_time
         FROM doctor_shifts
         WHERE doctor_id = :doctor_id AND shift_date >= CURDATE()
         ORDER BY shift_date, start_time'
    );
    $stmt->execute([':doctor_id' => (int)$doctorId]);
    $shifts = $stmt->fetchAll();

    if (!empty($shifts)) {
        $shiftRows = '';
        foreach ($shifts as $s) {
            $shiftRows .= '<tr>'
                . '<td>' . htmlspecialchars($s['shift_date'], ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars(substr($s['start_time'], 0, 5) . '–' . substr($s['end_time'], 0, 5), ENT_QUOTES) . '</td>'
                . '<td><form method="post" action="/services/doctor/update_shift.php" class="inline-form" data-confirm="Confermi la rimozione del turno?">'
                . csrfField()
                . '<input type="hidden" name="action" value="delete">'
                . '<input type="hidden" name="shift_id" value="' . (int)$s['shift_id'] . '">'
                . '<button type="submit" class="danger">Rimuovi</button>'
                . '</form></td>'
                . '</tr>';
        }
    }
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/doctor/update_shift');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('shift_rows', $shiftRows);
$tpl->setContent('csrf_field', csrfField());

renderHeader('I miei turni');
echo $tpl->get();
renderFooter();
