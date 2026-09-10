<?php
/**
 * public/services/patient/book_appointment.php
 *
 * Service registrato come 'patient_book_appointment'.
 * GET  -> elenca gli appointment_slots con status='open', filtrabili per
 *         specializzazione, e mostra il form di prenotazione.
 * POST -> prenota uno slot specifico per il paziente loggato, con
 *         transazione + lock pessimistico per evitare race condition su
 *         doppie prenotazioni dello stesso slot.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';
require_once __DIR__ . '/../../../include/badges.php';

requireService('patient_book_appointment');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

/**
 * Risolve il patient_id a partire dallo user_id loggato.
 * Ogni utente del gruppo Patient_Tier deve avere una riga in patients;
 * se manca (dato di setup incompleto), fermiamo con un messaggio chiaro
 * invece di un errore SQL opaco.
 */
function resolvePatientId(PDO $pdo, int $userId): ?int
{
    $stmt = $pdo->prepare('SELECT patient_id FROM patients WHERE user_id = :user_id LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

$patientId = resolvePatientId($pdo, $userId);
$feedback = null;
$feedbackType = 'info'; // info | error | success

if ($patientId === null) {
    $feedback = 'Il tuo profilo paziente non è ancora stato completato. Contatta l\'amministrazione.';
    $feedbackType = 'error';
}

// -----------------------------------------------------------------
// POST: prenotazione
// -----------------------------------------------------------------
if ($patientId !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);

    if (!$slotId) {
        $feedback = 'Slot non valido.';
        $feedbackType = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock pessimistico: impedisce a due richieste concorrenti di
            // prenotare lo stesso slot "open" contemporaneamente.
            $lockStmt = $pdo->prepare(
                'SELECT slot_id, status FROM appointment_slots WHERE slot_id = :slot_id FOR UPDATE'
            );
            $lockStmt->execute([':slot_id' => $slotId]);
            $slotRow = $lockStmt->fetch();

            if (!$slotRow) {
                $pdo->rollBack();
                $feedback = 'Lo slot selezionato non esiste più.';
                $feedbackType = 'error';
            } elseif ($slotRow['status'] !== 'open') {
                $pdo->rollBack();
                $feedback = 'Lo slot selezionato non è più disponibile: scegline un altro.';
                $feedbackType = 'error';
            } else {
                $updateStmt = $pdo->prepare(
                    "UPDATE appointment_slots SET status = 'booked', patient_id = :patient_id WHERE slot_id = :slot_id"
                );
                $updateStmt->execute([':patient_id' => $patientId, ':slot_id' => $slotId]);
                $pdo->commit();

                $feedback = 'Appuntamento prenotato con successo.';
                $feedbackType = 'success';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[book_appointment] ' . $e->getMessage());
            $feedback = 'Si è verificato un errore durante la prenotazione. Riprova.';
            $feedbackType = 'error';
        }
    }
}

// -----------------------------------------------------------------
// GET: elenco slot liberi (con filtro opzionale per specializzazione)
// -----------------------------------------------------------------
$specializationFilter = filter_input(INPUT_GET, 'specialization_id', FILTER_VALIDATE_INT) ?: null;

$specStmt = $pdo->query('SELECT specialization_id, specialization_name FROM specializations ORDER BY specialization_name');
$specializations = $specStmt->fetchAll();

$sql = "SELECT sl.slot_id, sl.slot_date, sl.slot_time,
               d.first_name, d.last_name, sp.specialization_name
        FROM appointment_slots sl
        JOIN doctors doc ON doc.doctor_id = sl.doctor_id
        JOIN users d ON d.user_id = doc.user_id
        JOIN specializations sp ON sp.specialization_id = doc.specialization_id
        WHERE sl.status = 'open' AND sl.slot_date >= CURDATE()";
$params = [];

if ($specializationFilter) {
    $sql .= ' AND doc.specialization_id = :specialization_id';
    $params[':specialization_id'] = $specializationFilter;
}

$sql .= ' ORDER BY sl.slot_date, sl.slot_time LIMIT 100';

$slotStmt = $pdo->prepare($sql);
$slotStmt->execute($params);
$slots = $slotStmt->fetchAll();

// -----------------------------------------------------------------
// Rendering
// -----------------------------------------------------------------

$specOptions = '<option value="">Tutte le specializzazioni</option>';
foreach ($specializations as $spec) {
    $selected = ($specializationFilter === (int)$spec['specialization_id']) ? ' selected' : '';
    $specOptions .= '<option value="' . (int)$spec['specialization_id'] . '"' . $selected . '>'
        . htmlspecialchars($spec['specialization_name'], ENT_QUOTES) . '</option>';
}

$slotRows = '';
if (empty($slots)) {
    $slotRows = '<tr><td colspan="5"><em>Nessuno slot disponibile per il filtro selezionato.</em></td></tr>';
} else {
    foreach ($slots as $slot) {
        $slotRows .= '<tr>'
            . '<td>' . htmlspecialchars($slot['slot_date'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars(substr($slot['slot_time'], 0, 5), ENT_QUOTES) . '</td>'
            . '<td>Dr. ' . htmlspecialchars($slot['first_name'] . ' ' . $slot['last_name'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($slot['specialization_name'], ENT_QUOTES) . '</td>'
            . '<td><form method="post" action="/services/patient/book_appointment.php" class="inline-form">'
            . csrfField()
            . '<input type="hidden" name="slot_id" value="' . (int)$slot['slot_id'] . '">'
            . '<button type="submit">Prenota</button>'
            . '</form></td>'
            . '</tr>';
    }
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/patient/book_appointment');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('specialization_options', $specOptions);
$tpl->setContent('specialization_selected', (string)($specializationFilter ?? ''));
$tpl->setContent('slot_rows', $slotRows);

renderHeader('Prenota una visita');
echo $tpl->get();
renderFooter();
