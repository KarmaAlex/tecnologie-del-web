<?php
/**
 * public/services/admin/crud_departments.php
 *
 * Service registrato come 'admin_crud_departments'.
 * Un unico controller gestisce create/update/delete via azione esplicita
 * nel POST (action=create|update|delete), più il GET che elenca i
 * dipartimenti esistenti e mostra il form.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('admin_crud_departments');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$feedback = null;
$feedbackType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';
    $name = trim((string)($_POST['department_name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);

    try {
        switch ($action) {
            case 'create':
                if ($name === '') {
                    $feedback = 'Il nome del dipartimento è obbligatorio.';
                    $feedbackType = 'error';
                } elseif (mb_strlen($name) > 100) {
                    $feedback = 'Il nome del dipartimento è troppo lungo (massimo 100 caratteri).';
                    $feedbackType = 'error';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO departments (department_name, description) VALUES (:department_name, :description)'
                    );
                    $stmt->execute([
                        ':department_name' => $name,
                        ':description'     => $description !== '' ? $description : null,
                    ]);
                    $feedback = 'Dipartimento creato con successo.';
                    $feedbackType = 'success';
                }
                break;

            case 'update':
                if (!$departmentId || $name === '') {
                    $feedback = 'Dati non validi per l\'aggiornamento.';
                    $feedbackType = 'error';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE departments SET department_name = :department_name, description = :description WHERE department_id = :department_id'
                    );
                    $stmt->execute([
                        ':department_name' => $name,
                        ':description'     => $description !== '' ? $description : null,
                        ':department_id'   => $departmentId,
                    ]);
                    $feedback = 'Dipartimento aggiornato con successo.';
                    $feedbackType = 'success';
                }
                break;

            case 'delete':
                if (!$departmentId) {
                    $feedback = 'Dipartimento non valido.';
                    $feedbackType = 'error';
                } else {
                    // Un dipartimento con specializzazioni collegate non può
                    // essere eliminato (RESTRICT sulla FK): intercettiamo
                    // l'errore per dare un messaggio comprensibile.
                    $stmt = $pdo->prepare('DELETE FROM departments WHERE department_id = :department_id');
                    $stmt->execute([':department_id' => $departmentId]);
                    $feedback = 'Dipartimento eliminato.';
                    $feedbackType = 'success';
                }
                break;

            default:
                $feedback = 'Azione non riconosciuta.';
                $feedbackType = 'error';
        }

        logAudit($userId, 'admin_crud_departments', 'ACCESS_GRANTED:' . $action);

    } catch (PDOException $e) {
        // Errno 1451 = MySQL foreign key constraint violation (RESTRICT)
        if ($e->getCode() === '23000') {
            $feedback = 'Impossibile eliminare: esistono specializzazioni collegate a questo dipartimento.';
        } else {
            error_log('[crud_departments] ' . $e->getMessage());
            $feedback = 'Si è verificato un errore. Riprova.';
        }
        $feedbackType = 'error';
    }
}

// -----------------------------------------------------------------
// GET: elenco dipartimenti
// -----------------------------------------------------------------
$departments = $pdo->query(
    'SELECT department_id, department_name, description FROM departments ORDER BY department_name'
)->fetchAll();

$rowsHtml = '';
if (empty($departments)) {
    $rowsHtml = '<tr><td colspan="4"><em>Nessun dipartimento registrato.</em></td></tr>';
} else {
    foreach ($departments as $d) {
        $descSafe = htmlspecialchars($d['description'] ?? '', ENT_QUOTES);
        $nameSafe = htmlspecialchars($d['department_name'], ENT_QUOTES);
        $id = (int)$d['department_id'];

        // Mapping consumato da MedCare.ui.initEditTriggers() in main.js:
        // ogni chiave è un data-attribute su questo bottone (letto da
        // row.dataset), il valore è il name del campo nel #department-form
        // da precompilare.
        $editValuesJson = htmlspecialchars(
            json_encode(['id' => 'department_id', 'name' => 'department_name', 'description' => 'description']),
            ENT_QUOTES
        );

        $rowsHtml .= '<tr>'
            . '<td>' . $nameSafe . '</td>'
            . '<td>' . ($descSafe !== '' ? $descSafe : '<span class="muted">—</span>') . '</td>'
            . '<td>'
            . '<button type="button" class="edit-trigger" data-id="' . $id . '" data-name="' . $nameSafe . '" data-description="' . $descSafe . '"'
            . ' data-edit-target="#department-form" data-edit-values="' . $editValuesJson . '">Modifica</button>'
            . '</td>'
            . '<td>'
            . '<form method="post" action="/services/admin/crud_departments.php" class="inline-form" data-confirm="Confermi l\'eliminazione del dipartimento?">'
            . csrfField()
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="department_id" value="' . $id . '">'
            . '<button type="submit" class="danger">Elimina</button>'
            . '</form>'
            . '</td>'
            . '</tr>';
    }
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/backend/admin/crud_departments');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('department_rows', $rowsHtml);
$tpl->setContent('csrf_field', csrfField());

renderHeader('Gestione dipartimenti', 'backend');
echo $tpl->get();
renderFooter();
