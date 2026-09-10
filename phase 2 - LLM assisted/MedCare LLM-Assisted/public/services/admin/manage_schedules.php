<?php
/**
 * public/services/admin/manage_schedules.php
 *
 * Service registrato come 'admin_manage_schedules'.
 * Diverso da doctor_update_shift (che è self-service per il singolo
 * medico): qui l'admin vede e gestisce i turni di TUTTI i medici della
 * clinica, con filtro opzionale per dipartimento.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('admin_manage_schedules');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$feedback = null;
$feedbackType = 'info';

// -----------------------------------------------------------------
// POST: aggiunta o rimozione turno
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
            $shiftDate = $_POST['shift_date'] ?? '';
            $startTime = $_POST['start_time'] ?? '';
            $endTime = $_POST['end_time'] ?? '';

            $dateOk = (bool)DateTime::createFromFormat('Y-m-d', $shiftDate);
            $startOk = (bool)DateTime::createFromFormat('H:i', $startTime);
            $endOk = (bool)DateTime::createFromFormat('H:i', $endTime);

            if (!$doctorId || !$dateOk || !$startOk || !$endOk) {
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
                    ':doctor_id'  => $doctorId,
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
                $stmt = $pdo->prepare('DELETE FROM doctor_shifts WHERE shift_id = :shift_id');
                $stmt->execute([':shift_id' => $shiftId]);
                $feedback = 'Turno rimosso.';
                $feedbackType = 'success';
            }
        } else {
            $feedback = 'Azione non riconosciuta.';
            $feedbackType = 'error';
        }

        logAudit($userId, 'admin_manage_schedules', 'ACCESS_GRANTED:' . $action);

    } catch (Throwable $e) {
        error_log('[manage_schedules] ' . $e->getMessage());
        $feedback = 'Si è verificato un errore. Riprova.';
        $feedbackType = 'error';
    }
}

// -----------------------------------------------------------------
// GET: filtro dipartimento + elenco turni
// -----------------------------------------------------------------
$departmentFilter = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT) ?: null;

$deptStmt = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name');
$departments = $deptStmt->fetchAll();

$deptOptions = '<option value="">Tutti i dipartimenti</option>';
foreach ($departments as $d) {
    $selected = ($departmentFilter === (int)$d['department_id']) ? ' selected' : '';
    $deptOptions .= '<option value="' . (int)$d['department_id'] . '"' . $selected . '>'
        . htmlspecialchars($d['department_name'], ENT_QUOTES) . '</option>';
}

$sql = "SELECT sh.shift_id, sh.shift_date, sh.start_time, sh.end_time,
               u.first_name, u.last_name, dep.department_name
        FROM doctor_shifts sh
        JOIN doctors doc ON doc.doctor_id = sh.doctor_id
        JOIN users u ON u.user_id = doc.user_id
        JOIN departments dep ON dep.department_id = doc.department_id
        WHERE sh.shift_date >= CURDATE()";
$params = [];

if ($departmentFilter) {
    $sql .= ' AND doc.department_id = :department_id';
    $params[':department_id'] = $departmentFilter;
}

$sql .= ' ORDER BY sh.shift_date, sh.start_time LIMIT 200';

$shiftStmt = $pdo->prepare($sql);
$shiftStmt->execute($params);
$shifts = $shiftStmt->fetchAll();

$shiftRows = '';
if (empty($shifts)) {
    $shiftRows = '<tr><td colspan="6"><em>Nessun turno programmato per il filtro selezionato.</em></td></tr>';
} else {
    foreach ($shifts as $s) {
        $shiftRows .= '<tr>'
            . '<td>' . htmlspecialchars($s['shift_date'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars(substr($s['start_time'], 0, 5) . '–' . substr($s['end_time'], 0, 5), ENT_QUOTES) . '</td>'
            . '<td>Dr. ' . htmlspecialchars($s['first_name'] . ' ' . $s['last_name'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($s['department_name'], ENT_QUOTES) . '</td>'
            . '<td><form method="post" action="/services/admin/manage_schedules.php" class="inline-form" data-confirm="Confermi la rimozione del turno?">'
            . csrfField()
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="shift_id" value="' . (int)$s['shift_id'] . '">'
            . '<button type="submit" class="danger">Rimuovi</button>'
            . '</form></td>'
            . '</tr>';
    }
}

// Elenco medici per la select del form "nuovo turno"
$doctorStmt = $pdo->query(
    'SELECT doc.doctor_id, u.first_name, u.last_name
     FROM doctors doc JOIN users u ON u.user_id = doc.user_id
     ORDER BY u.last_name, u.first_name'
);
$doctorOptions = '';
foreach ($doctorStmt->fetchAll() as $doc) {
    $doctorOptions .= '<option value="' . (int)$doc['doctor_id'] . '">'
        . htmlspecialchars($doc['last_name'] . ' ' . $doc['first_name'], ENT_QUOTES) . '</option>';
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/backend/admin/manage_schedules');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('department_options', $deptOptions);
$tpl->setContent('doctor_options', $doctorOptions);
$tpl->setContent('shift_rows', $shiftRows);
$tpl->setContent('csrf_field', csrfField());

renderHeader('Gestione turni', 'backend');
echo $tpl->get();
renderFooter();
