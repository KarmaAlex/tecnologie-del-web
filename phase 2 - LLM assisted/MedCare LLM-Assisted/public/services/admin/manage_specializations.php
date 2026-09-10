<?php
/**
 * public/services/admin/manage_specializations.php
 *
 * Service registrato come 'admin_manage_specializations'.
 * CRUD specializzazioni, stesso pattern di admin/crud_departments.php:
 * un unico controller con action=create|update|delete nel POST. Ogni
 * specializzazione richiede un dipartimento (FK NOT NULL), quindi il
 * form propone sempre la lista dei dipartimenti esistenti.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('admin_manage_specializations');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$feedback = null;
$feedbackType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';
    $name = trim((string)($_POST['specialization_name'] ?? ''));
    $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $specializationId = filter_input(INPUT_POST, 'specialization_id', FILTER_VALIDATE_INT);

    try {
        switch ($action) {
            case 'create':
                if ($name === '' || !$departmentId) {
                    $feedback = 'Nome e dipartimento sono obbligatori.';
                    $feedbackType = 'error';
                } elseif (mb_strlen($name) > 100) {
                    $feedback = 'Il nome della specializzazione è troppo lungo (massimo 100 caratteri).';
                    $feedbackType = 'error';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO specializations (specialization_name, department_id)
                         VALUES (:name, :department_id)'
                    );
                    $stmt->execute([':name' => $name, ':department_id' => $departmentId]);
                    $feedback = 'Specializzazione creata con successo.';
                    $feedbackType = 'success';
                }
                break;

            case 'update':
                if (!$specializationId || $name === '' || !$departmentId) {
                    $feedback = 'Dati non validi per l\'aggiornamento.';
                    $feedbackType = 'error';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE specializations SET specialization_name = :name, department_id = :department_id
                         WHERE specialization_id = :specialization_id'
                    );
                    $stmt->execute([
                        ':name'              => $name,
                        ':department_id'     => $departmentId,
                        ':specialization_id' => $specializationId,
                    ]);
                    $feedback = 'Specializzazione aggiornata con successo.';
                    $feedbackType = 'success';
                }
                break;

            case 'delete':
                if (!$specializationId) {
                    $feedback = 'Specializzazione non valida.';
                    $feedbackType = 'error';
                } else {
                    // Una specializzazione con medici collegati non può
                    // essere eliminata (RESTRICT sulla FK): intercettiamo
                    // l'errore per dare un messaggio comprensibile.
                    $stmt = $pdo->prepare('DELETE FROM specializations WHERE specialization_id = :specialization_id');
                    $stmt->execute([':specialization_id' => $specializationId]);
                    $feedback = 'Specializzazione eliminata.';
                    $feedbackType = 'success';
                }
                break;

            default:
                $feedback = 'Azione non riconosciuta.';
                $feedbackType = 'error';
        }

        logAudit($userId, 'admin_manage_specializations', 'ACCESS_GRANTED:' . $action);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $feedback = 'Impossibile eliminare: esistono medici collegati a questa specializzazione.';
        } else {
            error_log('[manage_specializations] ' . $e->getMessage());
            $feedback = 'Si è verificato un errore. Riprova.';
        }
        $feedbackType = 'error';
    }
}

// -----------------------------------------------------------------
// GET: elenco dipartimenti (per la select) e specializzazioni
// -----------------------------------------------------------------
$deptStmt = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name');
$departments = $deptStmt->fetchAll();

$departmentOptions = '';
foreach ($departments as $d) {
    $departmentOptions .= '<option value="' . (int)$d['department_id'] . '">'
        . htmlspecialchars($d['department_name'], ENT_QUOTES) . '</option>';
}

$specStmt = $pdo->query(
    'SELECT sp.specialization_id, sp.specialization_name, sp.department_id, dep.department_name
     FROM specializations sp
     JOIN departments dep ON dep.department_id = sp.department_id
     ORDER BY dep.department_name, sp.specialization_name'
);
$specializations = $specStmt->fetchAll();

$rowsHtml = '';
if (empty($specializations)) {
    $rowsHtml = '<tr><td colspan="4"><em>Nessuna specializzazione registrata.</em></td></tr>';
} else {
    foreach ($specializations as $s) {
        $nameSafe = htmlspecialchars($s['specialization_name'], ENT_QUOTES);
        $deptSafe = htmlspecialchars($s['department_name'], ENT_QUOTES);
        $id = (int)$s['specialization_id'];
        $deptId = (int)$s['department_id'];

        $editValuesJson = htmlspecialchars(
            json_encode(['id' => 'specialization_id', 'name' => 'specialization_name', 'dept' => 'department_id']),
            ENT_QUOTES
        );

        $rowsHtml .= '<tr>'
            . '<td>' . $nameSafe . '</td>'
            . '<td>' . $deptSafe . '</td>'
            . '<td>'
            . '<button type="button" class="edit-trigger" data-id="' . $id . '" data-name="' . $nameSafe . '" data-dept="' . $deptId . '"'
            . ' data-edit-target="#specialization-form" data-edit-values="' . $editValuesJson . '">Modifica</button>'
            . '</td>'
            . '<td>'
            . '<form method="post" action="/services/admin/manage_specializations.php" class="inline-form" data-confirm="Confermi l\'eliminazione della specializzazione?">'
            . csrfField()
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="specialization_id" value="' . $id . '">'
            . '<button type="submit" class="danger">Elimina</button>'
            . '</form>'
            . '</td>'
            . '</tr>';
    }
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/backend/admin/manage_specializations');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('department_options', $departmentOptions);
$tpl->setContent('specialization_rows', $rowsHtml);
$tpl->setContent('csrf_field', csrfField());

renderHeader('Gestione specializzazioni', 'backend');
echo $tpl->get();
renderFooter();
