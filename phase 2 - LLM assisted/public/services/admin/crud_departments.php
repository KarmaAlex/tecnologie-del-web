<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../include/session.php';
require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/functions.php';
require_once __DIR__ . '/../../../include/template2.inc.php';

bootstrapSession();

// Gatekeeper: only logged-in users whose groups authorize this service may proceed.
$user = requireService();

$pdo = getPDO();

$formMessage = '';
$formMessageClass = 'is-hidden';

/**
 * Fetches all departments, most recently created last (id ascending).
 */
function fetchDepartments(PDO $pdo): array
{
	$stmt = $pdo->query('SELECT id, name, description FROM departments ORDER BY name ASC');

	return $stmt->fetchAll();
}

function fetchDepartmentById(PDO $pdo, int $id): ?array
{
	$stmt = $pdo->prepare('SELECT id, name, description FROM departments WHERE id = :id LIMIT 1');
	$stmt->execute([':id' => $id]);
	$row = $stmt->fetch();

	return $row !== false ? $row : null;
}

$editingDepartment = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = (string)($_POST['csrf_token'] ?? '');
	$action = (string)($_POST['action'] ?? '');

	if (!verifyCsrfToken($token)) {
		$formMessage = 'Sessione scaduta, riprova.';
		$formMessageClass = 'bk-alert-error';
	} elseif ($action === 'create' || $action === 'update') {
		$name = trim((string)($_POST['name'] ?? ''));
		$description = trim((string)($_POST['description'] ?? ''));
		$departmentId = (int)($_POST['department_id'] ?? 0);

		if ($name === '') {
			$formMessage = 'Il nome del reparto è obbligatorio.';
			$formMessageClass = 'bk-alert-error';
		} else {
			try {
				if ($action === 'update' && $departmentId > 0) {
					$stmt = $pdo->prepare('UPDATE departments SET name = :name, description = :description WHERE id = :id');
					$stmt->execute([':name' => $name, ':description' => $description, ':id' => $departmentId]);
					$formMessage = 'Reparto aggiornato.';
				} else {
					$stmt = $pdo->prepare('INSERT INTO departments (name, description) VALUES (:name, :description)');
					$stmt->execute([':name' => $name, ':description' => $description]);
					$formMessage = 'Reparto creato.';
				}

				$formMessageClass = 'bk-alert-success';
			} catch (Throwable $e) {
				error_log('Department save error: ' . $e->getMessage());
				$formMessage = 'Nome reparto già esistente o dati non validi.';
				$formMessageClass = 'bk-alert-error';
			}
		}
	} elseif ($action === 'delete') {
		$departmentId = (int)($_POST['department_id'] ?? 0);

		if ($departmentId > 0) {
			try {
				$stmt = $pdo->prepare('DELETE FROM departments WHERE id = :id');
				$stmt->execute([':id' => $departmentId]);
				$formMessage = 'Reparto eliminato.';
				$formMessageClass = 'bk-alert-success';
			} catch (Throwable $e) {
				error_log('Department delete error: ' . $e->getMessage());
				$formMessage = 'Impossibile eliminare: verifica che nessun medico sia ancora assegnato al reparto.';
				$formMessageClass = 'bk-alert-error';
			}
		}
	}
}

if (isset($_GET['edit'])) {
	$editingDepartment = fetchDepartmentById($pdo, (int)$_GET['edit']);
}

$departments = fetchDepartments($pdo);

$content = new Template(__DIR__ . '/../../../skins/backend/departments');
$content->setContent('CSRF_TOKEN', csrfToken());

if ($editingDepartment !== null) {
	$content->setContent('FORM_HEADING', 'Modifica reparto');
	$content->setContent('FORM_ACTION', 'update');
	$content->setContent('FORM_DEPARTMENT_ID', (string)$editingDepartment['id']);
	$content->setContent('FORM_NAME', esc((string)$editingDepartment['name']));
	$content->setContent('FORM_DESCRIPTION', esc((string)($editingDepartment['description'] ?? '')));
	$content->setContent('FORM_SUBMIT_LABEL', 'Salva modifiche');
	$content->setContent('CANCEL_EDIT_HTML', '<a class="bk-btn bk-btn-ghost" href="crud_departments.php">Annulla</a>');
} else {
	$content->setContent('FORM_HEADING', 'Nuovo reparto');
	$content->setContent('FORM_ACTION', 'create');
	$content->setContent('FORM_DEPARTMENT_ID', '');
	$content->setContent('FORM_NAME', '');
	$content->setContent('FORM_DESCRIPTION', '');
	$content->setContent('FORM_SUBMIT_LABEL', 'Crea reparto');
	$content->setContent('CANCEL_EDIT_HTML', '');
}

$content->setContent('HAS_DEPARTMENTS', empty($departments) ? '' : '1');

foreach ($departments as $dept) {
	$actionsHtml = '<a class="bk-btn bk-btn-ghost bk-btn-small" href="crud_departments.php?edit=' . (int)$dept['id'] . '">Modifica</a> '
		. '<form class="bk-inline-form" method="post" action="crud_departments.php" onsubmit="return confirm(\'Eliminare questo reparto?\');">'
		. '<input type="hidden" name="csrf_token" value="' . esc(csrfToken()) . '">'
		. '<input type="hidden" name="action" value="delete">'
		. '<input type="hidden" name="department_id" value="' . (int)$dept['id'] . '">'
		. '<button type="submit" class="bk-btn bk-btn-danger bk-btn-small">Elimina</button>'
		. '</form>';

	$content->setContent('DEPT_NAME', esc((string)$dept['name']));
	$content->setContent('DEPT_DESCRIPTION', esc((string)($dept['description'] ?? '')));
	$content->setContent('DEPT_ACTIONS_HTML', $actionsHtml);
}

$contentHtml = $content->get();

$dashboard = new Template(__DIR__ . '/../../../skins/backend/dashboard');
$dashboard->setContent('PAGE_TITLE', 'MedCare Backoffice - Reparti');
$dashboard->setContent('PAGE_HEADING', 'Gestione reparti');
$dashboard->setContent('PAGE_LEAD', 'Crea, modifica ed elimina i reparti dell\'ospedale.');
$dashboard->setContent('NAV_LINKS_HTML', renderBackendNav((string)$user['role'], 'crud_departments.php'));
$dashboard->setContent('NAV_WELCOME', esc((string)($user['full_name'] ?? $user['username'])));
$dashboard->setContent('FORM_MESSAGE', esc($formMessage));
$dashboard->setContent('FORM_MESSAGE_CLASS', $formMessageClass);
$dashboard->setContent('PAGE_CONTENT', $contentHtml);

$dashboard->close();
