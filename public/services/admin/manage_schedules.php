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

function fetchDoctors(PDO $pdo): array
{
	$sql = 'SELECT dp.id, u.full_name, d.name AS department_name,
				sp.name AS specialization_name
			FROM doctor_profiles dp
			INNER JOIN users u ON u.id = dp.user_id
			LEFT JOIN departments d ON d.id = dp.department_id
			LEFT JOIN specializations sp ON sp.id = dp.specialization_id
			ORDER BY u.full_name ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute();

	return $stmt->fetchAll();
}

function fetchScheduleById(PDO $pdo, int $scheduleId): ?array
{
	$stmt = $pdo->prepare('SELECT id, doctor_id, start_at, end_at, location FROM schedules WHERE id = :id LIMIT 1');
	$stmt->execute([':id' => $scheduleId]);
	$row = $stmt->fetch();

	return $row !== false ? $row : null;
}

function fetchSchedules(PDO $pdo, ?int $doctorId = null): array
{
	$sql = 'SELECT sc.id, sc.doctor_id, sc.start_at, sc.end_at, sc.location,
				u.full_name AS doctor_name,
				d.name AS department_name
			FROM schedules sc
			INNER JOIN doctor_profiles dp ON dp.id = sc.doctor_id
			INNER JOIN users u ON u.id = dp.user_id
			LEFT JOIN departments d ON d.id = dp.department_id';

	$params = [];
	if ($doctorId !== null && $doctorId > 0) {
		$sql .= ' WHERE dp.id = :doctor_id';
		$params[':doctor_id'] = $doctorId;
	}

	$sql .= ' ORDER BY sc.start_at DESC';
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll();
}

$formMessage = '';
$formMessageClass = 'alert is-hidden';
$doctorId = (int)($_GET['doctor_id'] ?? $_POST['doctor_id'] ?? 0);
$startAt = trim((string)($_POST['start_at'] ?? ''));
$endAt = trim((string)($_POST['end_at'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));
$editingScheduleId = (int)($_GET['edit'] ?? $_POST['schedule_id'] ?? 0);
$formAction = 'create';

if ($startAt !== '') {
	$startAt = str_replace('T', ' ', $startAt);
	$startAt = date('Y-m-d H:i:s', strtotime($startAt));
}
if ($endAt !== '') {
	$endAt = str_replace('T', ' ', $endAt);
	$endAt = date('Y-m-d H:i:s', strtotime($endAt));
}

if ($editingScheduleId > 0) {
	$editingSchedule = fetchScheduleById($pdo, $editingScheduleId);
	if ($editingSchedule !== null) {
		$formAction = 'update';
		$doctorId = (int)$editingSchedule['doctor_id'];
		$startAt = (string)$editingSchedule['start_at'];
		$endAt = (string)$editingSchedule['end_at'];
		$location = (string)($editingSchedule['location'] ?? '');
	}
}

if ($startAt !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startAt)) {
	$startAt = date('Y-m-d\TH:i', strtotime($startAt));
}
if ($endAt !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endAt)) {
	$endAt = date('Y-m-d\TH:i', strtotime($endAt));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = (string)($_POST['csrf_token'] ?? '');
	$action = (string)($_POST['action'] ?? '');
	$doctorId = (int)($_POST['doctor_id'] ?? 0);
	$startAt = trim((string)($_POST['start_at'] ?? ''));
	$endAt = trim((string)($_POST['end_at'] ?? ''));
	$location = trim((string)($_POST['location'] ?? ''));
	$editingScheduleId = (int)($_POST['schedule_id'] ?? 0);

	if (!verifyCsrfToken($token)) {
		$formMessage = 'Sessione scaduta, riprova.';
		$formMessageClass = 'alert alert-error';
	} elseif ($action === 'create' || $action === 'update') {
		if ($doctorId <= 0 || $startAt === '' || $endAt === '') {
			$formMessage = 'Seleziona un medico e inserisci una data di inizio e fine.';
			$formMessageClass = 'alert alert-error';
		} elseif (strtotime($startAt) === false || strtotime($endAt) === false || strtotime($endAt) <= strtotime($startAt)) {
			$formMessage = 'La fascia oraria non è valida. La fine deve essere successiva all\'inizio.';
			$formMessageClass = 'alert alert-error';
		} else {
			if ($action === 'create') {
				$stmt = $pdo->prepare('INSERT INTO schedules (doctor_id, start_at, end_at, location) VALUES (:doctor_id, :start_at, :end_at, :location)');
				$stmt->execute([
					':doctor_id' => $doctorId,
					':start_at' => $startAt,
					':end_at' => $endAt,
					':location' => $location,
				]);
				$formMessage = 'Disponibilità creata correttamente.';
			} else {
				$stmt = $pdo->prepare('UPDATE schedules SET doctor_id = :doctor_id, start_at = :start_at, end_at = :end_at, location = :location WHERE id = :id');
				$stmt->execute([
					':doctor_id' => $doctorId,
					':start_at' => $startAt,
					':end_at' => $endAt,
					':location' => $location,
					':id' => $editingScheduleId,
				]);
				$formMessage = 'Disponibilità aggiornata correttamente.';
			}
			$formMessageClass = 'alert alert-success';
			$startAt = '';
			$endAt = '';
			$location = '';
			$doctorId = 0;
			$editingScheduleId = 0;
			$formAction = 'create';
		}
	} elseif ($action === 'delete') {
		$deleteId = (int)($_POST['schedule_id'] ?? 0);
		if ($deleteId <= 0) {
			$formMessage = 'Seleziona una disponibilità da eliminare.';
			$formMessageClass = 'alert alert-error';
		} else {
			$stmt = $pdo->prepare('DELETE FROM schedules WHERE id = :id');
			$stmt->execute([':id' => $deleteId]);
			$formMessage = 'Disponibilità eliminata.';
			$formMessageClass = 'alert alert-success';
		}
	}
}

$doctors = fetchDoctors($pdo);
$schedules = fetchSchedules($pdo, $doctorId > 0 ? $doctorId : null);

$view = new Template(__DIR__ . '/../../../skins/backend/schedules');
$view->setContent('PAGE_STYLES', (string)file_get_contents(__DIR__ . '/../../../skins/backend/dashboard.css'));
$view->setContent('PAGE_HEADING', 'Gestione disponibilità mediche');
$view->setContent('PAGE_LEAD', 'Crea, aggiorna e gestisci le fasce orarie disponibili per i professionisti sanitari del sistema.');
$view->setContent('FORM_MESSAGE', $formMessage);
$view->setContent('FORM_MESSAGE_CLASS', $formMessageClass);
$view->setContent('FORM_CSRF_TOKEN', csrfToken());
$view->setContent('DELETE_CSRF_TOKEN', csrfToken());
$view->setContent('FORM_ACTION', $formAction);
$view->setContent('SCHEDULE_ID_INPUT', (string)$editingScheduleId);
$view->setContent('DOCTOR_ID_INPUT', (string)$doctorId);
$view->setContent('START_AT_VALUE', esc($startAt));
$view->setContent('END_AT_VALUE', esc($endAt));
$view->setContent('LOCATION_VALUE', esc($location));
$view->setContent('HAS_DOCTORS', empty($doctors) ? '' : '1');
$view->setContent('HAS_SCHEDULES', empty($schedules) ? '' : '1');

foreach ($doctors as $doctor) {
	$selected = ((int)$doctor['id'] === $doctorId) ? 'selected' : '';
	$view->setContent('DOCTOR_OPTION_ID', (string)$doctor['id']);
	$view->setContent('DOCTOR_OPTION_LABEL', esc((string)$doctor['full_name'] . ' - ' . ($doctor['department_name'] ?? 'N/D')));
	$view->setContent('DOCTOR_OPTION_SELECTED', $selected);
}

foreach ($schedules as $schedule) {
	$view->setContent('SCHEDULE_ID', (string)$schedule['id']);
	$view->setContent('SCHEDULE_DOCTOR_NAME', esc((string)$schedule['doctor_name']));
	$view->setContent('SCHEDULE_DEPARTMENT', esc((string)($schedule['department_name'] ?? 'N/D')));
	$view->setContent('SCHEDULE_START', esc(formatDateTimeIt((string)$schedule['start_at'])));
	$view->setContent('SCHEDULE_END', esc(formatDateTimeIt((string)$schedule['end_at'])));
	$view->setContent('SCHEDULE_LOCATION', esc((string)($schedule['location'] ?? 'N/D')));
	$view->setContent('SCHEDULE_EDIT_URL', 'manage_schedules.php?edit=' . (int)$schedule['id']);
	$view->setContent('SCHEDULE_DELETE_ID', (string)$schedule['id']);
}

$contentHtml = $view->get();

$base = new Template(__DIR__ . '/../../../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Gestione disponibilità');
$base->setContent('META_DESCRIPTION', 'Gestisci i turni e le disponibilità dei medici in MedCare Portal.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Ciao, ' . esc((string)($user['full_name'] ?? $user['username'])));
populateBaseNavigation($base, (string)($user['role'] ?? 'admin'), '../../logout.php', 'Esci');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));
$base->close();
