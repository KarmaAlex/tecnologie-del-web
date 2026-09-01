<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../include/session.php';
require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/functions.php';
require_once __DIR__ . '/../../../include/template2.inc.php';

bootstrapSession();
$user = requireService();
$pdo = getPDO();

function fetchDepartments(PDO $pdo): array
{
	$sql = 'SELECT d.id, d.name, d.description,
				(SELECT COUNT(*) FROM doctor_profiles dp WHERE dp.department_id = d.id) AS doctor_count
			FROM departments d
			ORDER BY d.name ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute();

	return $stmt->fetchAll();
}

function fetchDoctors(PDO $pdo): array
{
	$sql = 'SELECT dp.id, dp.department_id, u.full_name,
				sp.name AS specialization_name,
				d.name AS department_name
			FROM doctor_profiles dp
			INNER JOIN users u ON u.id = dp.user_id
			LEFT JOIN specializations sp ON sp.id = dp.specialization_id
			LEFT JOIN departments d ON d.id = dp.department_id
			ORDER BY u.full_name ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute();

	return $stmt->fetchAll();
}

function departmentExists(PDO $pdo, int $departmentId): bool
{
	$stmt = $pdo->prepare('SELECT id FROM departments WHERE id = :id LIMIT 1');
	$stmt->execute([':id' => $departmentId]);
	return $stmt->fetch() !== false;
}

$formMessage = '';
$formMessageClass = 'alert is-hidden';
$selectedDepartmentId = (int)($_POST['department_id'] ?? $_GET['department_id'] ?? 0);
$departmentName = trim((string)($_POST['name'] ?? ''));
$departmentDescription = trim((string)($_POST['description'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = (string)($_POST['csrf_token'] ?? '');
	$action = (string)($_POST['action'] ?? '');
	$selectedDepartmentId = (int)($_POST['department_id'] ?? 0);
	$departmentName = trim((string)($_POST['name'] ?? ''));
	$departmentDescription = trim((string)($_POST['description'] ?? ''));

	if (!verifyCsrfToken($token)) {
		$formMessage = 'Sessione scaduta, riprova.';
		$formMessageClass = 'alert alert-error';
	} elseif ($action === 'create') {
		if ($departmentName === '') {
			$formMessage = 'Inserisci un nome reparto valido.';
			$formMessageClass = 'alert alert-error';
		} else {
			$stmt = $pdo->prepare('INSERT INTO departments (name, description) VALUES (:name, :description)');
			$stmt->execute([
				':name' => $departmentName,
				':description' => $departmentDescription,
			]);
			$formMessage = 'Reparto creato correttamente.';
			$formMessageClass = 'alert alert-success';
			$departmentName = '';
			$departmentDescription = '';
		}
	} elseif ($action === 'update') {
		if ($selectedDepartmentId <= 0 || $departmentName === '') {
			$formMessage = 'Seleziona un reparto e inserisci un nome valido.';
			$formMessageClass = 'alert alert-error';
		} else {
			$stmt = $pdo->prepare('UPDATE departments SET name = :name, description = :description WHERE id = :id');
			$stmt->execute([
				':name' => $departmentName,
				':description' => $departmentDescription,
				':id' => $selectedDepartmentId,
			]);
			$formMessage = 'Reparto aggiornato correttamente.';
			$formMessageClass = 'alert alert-success';
		}
	} elseif ($action === 'delete') {
		if ($selectedDepartmentId <= 0) {
			$formMessage = 'Seleziona un reparto da eliminare.';
			$formMessageClass = 'alert alert-error';
		} else {
			$stmt = $pdo->prepare('DELETE FROM departments WHERE id = :id AND NOT EXISTS (SELECT 1 FROM doctor_profiles WHERE department_id = :id)');
			$stmt->execute([':id' => $selectedDepartmentId]);
			if ($stmt->rowCount() > 0) {
				$formMessage = 'Reparto eliminato.';
				$formMessageClass = 'alert alert-success';
			} else {
				$formMessage = 'Impossibile eliminare il reparto perché è ancora associato a un medico.';
				$formMessageClass = 'alert alert-error';
			}
		}
	} elseif ($action === 'assign_doctor') {
		$doctorId = (int)($_POST['doctor_id'] ?? 0);
		if ($selectedDepartmentId <= 0 || $doctorId <= 0 || !departmentExists($pdo, $selectedDepartmentId)) {
			$formMessage = 'Seleziona un reparto e un medico validi.';
			$formMessageClass = 'alert alert-error';
		} else {
			$stmt = $pdo->prepare('UPDATE doctor_profiles SET department_id = :department_id WHERE id = :doctor_id');
			$stmt->execute([
				':department_id' => $selectedDepartmentId,
				':doctor_id' => $doctorId,
			]);
			$formMessage = $stmt->rowCount() > 0
				? 'Medico assegnato al reparto.'
				: 'Il medico è già assegnato a questo reparto.';
			$formMessageClass = 'alert alert-success';
		}
	} elseif ($action === 'unassign_doctor') {
		$doctorId = (int)($_POST['doctor_id'] ?? 0);
		if ($selectedDepartmentId <= 0 || $doctorId <= 0) {
			$formMessage = 'Seleziona un reparto e un medico validi.';
			$formMessageClass = 'alert alert-error';
		} else {
			$stmt = $pdo->prepare('UPDATE doctor_profiles
				SET department_id = NULL
				WHERE id = :doctor_id AND department_id = :department_id');
			$stmt->execute([
				':doctor_id' => $doctorId,
				':department_id' => $selectedDepartmentId,
			]);
			$formMessage = $stmt->rowCount() > 0
				? 'Medico rimosso dal reparto.'
				: 'Il medico non risulta assegnato a questo reparto.';
			$formMessageClass = 'alert alert-success';
		}
	}
}

$departments = fetchDepartments($pdo);
$doctors = fetchDoctors($pdo);
$doctorsJson = json_encode($doctors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$view = new Template(__DIR__ . '/../../../skins/backend/dashboard');
$view->setContent('PAGE_STYLES', (string)file_get_contents(__DIR__ . '/../../../skins/backend/dashboard.css'));
$view->setContent('PAGE_HEADING', 'Gestione reparti');
$view->setContent('PAGE_LEAD', 'Crea, aggiorna e gestisci i reparti clinici del portale sanitario.');
$view->setContent('FORM_MESSAGE', $formMessage);
$view->setContent('FORM_MESSAGE_CLASS', $formMessageClass);
$view->setContent('CREATE_CSRF_TOKEN', csrfToken());
$view->setContent('DELETE_CSRF_TOKEN', csrfToken());
$view->setContent('ASSIGN_CSRF_TOKEN', csrfToken());
$view->setContent('DOCTORS_JSON', $doctorsJson !== false ? $doctorsJson : '[]');
$view->setContent('DEPARTMENT_NAME', esc($departmentName));
$view->setContent('DEPARTMENT_DESCRIPTION', esc($departmentDescription));
$view->setContent('HAS_DEPARTMENTS', empty($departments) ? '' : '1');

foreach ($departments as $department) {
	$view->setContent('DEPT_ID', (string)$department['id']);
	$view->setContent('DEPT_NAME', esc((string)$department['name']));
	$view->setContent('DEPT_DESCRIPTION', esc((string)($department['description'] ?? '')));
	$view->setContent('DEPT_DOCTOR_COUNT', (string)$department['doctor_count']);
}

$contentHtml = $view->get();

$base = new Template(__DIR__ . '/../../../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Gestione reparti');
$base->setContent('META_DESCRIPTION', 'Gestisci reparti, specializzazioni e organizzazione sanitaria da MedCare Portal.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Ciao, ' . esc((string)($user['full_name'] ?? $user['username'])));
populateBaseNavigation($base, (string)($user['role'] ?? 'admin'), '../../logout.php', 'Esci');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));
$base->close();
