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
 * Fetches all doctors with their display label (name + specialization).
 */
function fetchDoctorsForSelect(PDO $pdo): array
{
	$sql = 'SELECT dp.id, u.full_name, sp.name AS specialization_name
			FROM doctor_profiles dp
			INNER JOIN users u ON u.id = dp.user_id
			LEFT JOIN specializations sp ON sp.id = dp.specialization_id
			ORDER BY u.full_name ASC';

	return $pdo->query($sql)->fetchAll();
}

/**
 * Fetches all schedules with doctor name and how many appointments were
 * booked against each one.
 */
function fetchSchedulesOverview(PDO $pdo): array
{
	$sql = 'SELECT sc.id, sc.start_at, sc.end_at, sc.location,
				u.full_name AS doctor_name,
				(SELECT COUNT(*) FROM appointments a WHERE a.schedule_id = sc.id AND a.status <> \'cancelled\') AS booked_count
			FROM schedules sc
			LEFT JOIN doctor_profiles dp ON dp.id = sc.doctor_id
			LEFT JOIN users u ON u.id = dp.user_id
			ORDER BY sc.start_at DESC';

	return $pdo->query($sql)->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = (string)($_POST['csrf_token'] ?? '');
	$action = (string)($_POST['action'] ?? '');

	if (!verifyCsrfToken($token)) {
		$formMessage = 'Sessione scaduta, riprova.';
		$formMessageClass = 'bk-alert-error';
	} elseif ($action === 'create') {
		$doctorId = (int)($_POST['doctor_id'] ?? 0);
		$startAt = (string)($_POST['start_at'] ?? '');
		$endAt = (string)($_POST['end_at'] ?? '');
		$location = trim((string)($_POST['location'] ?? ''));

		$startTs = strtotime(str_replace('T', ' ', $startAt));
		$endTs = strtotime(str_replace('T', ' ', $endAt));

		if ($doctorId <= 0 || $startTs === false || $endTs === false) {
			$formMessage = 'Seleziona un medico e date valide.';
			$formMessageClass = 'bk-alert-error';
		} elseif ($endTs <= $startTs) {
			$formMessage = 'L\'orario di fine deve essere successivo a quello di inizio.';
			$formMessageClass = 'bk-alert-error';
		} else {
			try {
				$stmt = $pdo->prepare(
					'INSERT INTO schedules (doctor_id, start_at, end_at, location) VALUES (:doctor_id, :start_at, :end_at, :location)'
				);
				$stmt->execute([
					':doctor_id' => $doctorId,
					':start_at' => date('Y-m-d H:i:s', $startTs),
					':end_at' => date('Y-m-d H:i:s', $endTs),
					':location' => $location !== '' ? $location : null,
				]);
				$formMessage = 'Disponibilità creata.';
				$formMessageClass = 'bk-alert-success';
			} catch (Throwable $e) {
				error_log('Schedule create error: ' . $e->getMessage());
				$formMessage = 'Impossibile creare la disponibilità.';
				$formMessageClass = 'bk-alert-error';
			}
		}
	} elseif ($action === 'delete') {
		$scheduleId = (int)($_POST['schedule_id'] ?? 0);

		if ($scheduleId > 0) {
			try {
				$stmt = $pdo->prepare('DELETE FROM schedules WHERE id = :id');
				$stmt->execute([':id' => $scheduleId]);
				$formMessage = 'Disponibilità eliminata.';
				$formMessageClass = 'bk-alert-success';
			} catch (Throwable $e) {
				error_log('Schedule delete error: ' . $e->getMessage());
				$formMessage = 'Impossibile eliminare: verifica non ci siano prenotazioni collegate.';
				$formMessageClass = 'bk-alert-error';
			}
		}
	}
}

$doctors = fetchDoctorsForSelect($pdo);
$schedules = fetchSchedulesOverview($pdo);

$content = new Template(__DIR__ . '/../../../skins/backend/schedules');
$content->setContent('CSRF_TOKEN', csrfToken());

if (empty($doctors)) {
	$content->setContent('DOC_ID', '');
	$content->setContent('DOC_LABEL', 'Nessun medico registrato');
} else {
	foreach ($doctors as $doc) {
		$label = $doc['full_name'] . (!empty($doc['specialization_name']) ? ' - ' . $doc['specialization_name'] : '');
		$content->setContent('DOC_ID', (string)$doc['id']);
		$content->setContent('DOC_LABEL', esc($label));
	}
}

$content->setContent('HAS_SCHEDULES', empty($schedules) ? '' : '1');

foreach ($schedules as $sched) {
	$deleteHtml = '<form class="bk-inline-form" method="post" action="manage_schedules.php" onsubmit="return confirm(\'Eliminare questa disponibilità?\');">'
		. '<input type="hidden" name="csrf_token" value="' . esc(csrfToken()) . '">'
		. '<input type="hidden" name="action" value="delete">'
		. '<input type="hidden" name="schedule_id" value="' . (int)$sched['id'] . '">'
		. '<button type="submit" class="bk-btn bk-btn-danger bk-btn-small">Elimina</button>'
		. '</form>';

	$content->setContent('SCHED_DOCTOR', esc((string)($sched['doctor_name'] ?? 'N/D')));
	$content->setContent('SCHED_START', esc(formatDateTimeIt((string)$sched['start_at'])));
	$content->setContent('SCHED_END', esc(formatDateTimeIt((string)$sched['end_at'])));
	$content->setContent('SCHED_LOCATION', esc((string)($sched['location'] ?? '')));
	$content->setContent('SCHED_BOOKED_COUNT', (string)$sched['booked_count']);
	$content->setContent('SCHED_DELETE_HTML', $deleteHtml);
}

$contentHtml = $content->get();

$dashboard = new Template(__DIR__ . '/../../../skins/backend/dashboard');
$dashboard->setContent('PAGE_TITLE', 'MedCare Backoffice - Disponibilità mediche');
$dashboard->setContent('PAGE_HEADING', 'Gestione disponibilità mediche');
$dashboard->setContent('PAGE_LEAD', 'Pianifica le fasce orarie in cui i pazienti possono prenotare una visita.');
$dashboard->setContent('NAV_LINKS_HTML', renderBackendNav((string)$user['role'], 'manage_schedules.php'));
$dashboard->setContent('NAV_WELCOME', esc((string)($user['full_name'] ?? $user['username'])));
$dashboard->setContent('FORM_MESSAGE', esc($formMessage));
$dashboard->setContent('FORM_MESSAGE_CLASS', $formMessageClass);
$dashboard->setContent('PAGE_CONTENT', $contentHtml);

$dashboard->close();
