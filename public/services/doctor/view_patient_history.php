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

function fetchDoctorProfileId(PDO $pdo, int $userId): ?int
{
	$sql = 'SELECT id
			FROM doctor_profiles
			WHERE user_id = :user_id
			LIMIT 1';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':user_id' => $userId]);
	$row = $stmt->fetch();

	return $row !== false ? (int)$row['id'] : null;
}

function fetchDoctorPatients(PDO $pdo, int $doctorId): array
{
	$sql = 'SELECT ap.patient_id,
				pp.id AS patient_profile_id,
				u.full_name AS patient_name,
				MAX(ap.appointment_at) AS last_visit,
				COUNT(ap.id) AS appointment_count,
				(SELECT ml.note
				 FROM medical_logs ml
				 WHERE ml.patient_id = pp.id AND ml.doctor_id = :doctor_id_subquery
				 ORDER BY ml.created_at DESC
				 LIMIT 1) AS last_note
			FROM appointments ap
			INNER JOIN patient_profiles pp ON pp.id = ap.patient_id
			INNER JOIN users u ON u.id = pp.user_id
			WHERE ap.doctor_id = :doctor_id
			GROUP BY ap.patient_id, pp.id, u.full_name
			ORDER BY MAX(ap.appointment_at) DESC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':doctor_id' => $doctorId,
		':doctor_id_subquery' => $doctorId,
	]);

	return $stmt->fetchAll();
}

function fetchCustomerLogs(PDO $pdo, int $doctorId, int $patientProfileId): array
{
	$sql = 'SELECT ml.id, ml.note, ml.created_at, u.full_name AS patient_name,
				ap.appointment_at, ap.reason
			FROM medical_logs ml
			INNER JOIN patient_profiles pp ON pp.id = ml.patient_id
			INNER JOIN users u ON u.id = pp.user_id
			LEFT JOIN appointments ap ON ap.id = ml.appointment_id
			WHERE ml.doctor_id = :doctor_id AND ml.patient_id = :patient_id
			ORDER BY ml.created_at DESC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':doctor_id' => $doctorId,
		':patient_id' => $patientProfileId,
	]);

	return $stmt->fetchAll();
}

$doctorProfileId = fetchDoctorProfileId($pdo, (int)($user['id'] ?? 0));
if ($doctorProfileId === null) {
	denyAccess();
}

$patientRows = fetchDoctorPatients($pdo, $doctorProfileId);
$selectedPatientId = (int)($_GET['patient_id'] ?? 0);
if ($selectedPatientId <= 0 && !empty($patientRows)) {
	$selectedPatientId = (int)$patientRows[0]['patient_profile_id'];
}

$selectedLogs = [];
if ($selectedPatientId > 0) {
	$selectedLogs = fetchCustomerLogs($pdo, $doctorProfileId, $selectedPatientId);
}

$view = new Template(__DIR__ . '/../../../skins/backend/patient_log');
$view->setContent('PAGE_STYLES', (string)file_get_contents(__DIR__ . '/../../../skins/backend/patient_log.css'));
$view->setContent('PAGE_HEADING', 'Storico pazienti');
$view->setContent('PAGE_LEAD', 'Consulta le visite, i registri clinici e la cronologia dei pazienti assegnati al tuo profilo medico.');
$view->setContent('DOCTOR_NAME', esc((string)($user['full_name'] ?? $user['username'])));
$view->setContent('HAS_PATIENTS', empty($patientRows) ? '' : '1');
$view->setContent('PATIENT_COUNT', (string)count($patientRows));
$view->setContent('VISIT_COUNT', (string)array_sum(array_map(static fn($row): int => (int)$row['appointment_count'], $patientRows)));
$view->setContent('SELECTED_PATIENT_ID', (string)$selectedPatientId);

foreach ($patientRows as $patient) {
	$selected = (int)$patient['patient_profile_id'] === $selectedPatientId ? 'selected' : '';
	$view->setContent('PATIENT_OPTION_ID', (string)$patient['patient_profile_id']);
	$view->setContent('PATIENT_OPTION_NAME', esc((string)$patient['patient_name']));
	$view->setContent('PATIENT_OPTION_SELECTED', $selected);
	$view->setContent('PATIENT_ROW_NAME', esc((string)$patient['patient_name']));
	$view->setContent('PATIENT_ROW_LAST_VISIT', esc(formatDateTimeIt((string)$patient['last_visit'])));
	$view->setContent('PATIENT_ROW_APPOINTMENTS', esc((string)$patient['appointment_count']));
	$view->setContent('PATIENT_ROW_LAST_NOTE', esc((string)($patient['last_note'] ?? 'Nessuna nota clinica registrata')));
}

$view->setContent('HAS_SELECTED_PATIENT', empty($selectedLogs) ? '' : '1');
$view->setContent('SELECTED_PATIENT_NAME', esc((string)($selectedLogs[0]['patient_name'] ?? 'Paziente selezionato')));

foreach ($selectedLogs as $log) {
	$noteText = trim((string)($log['note'] ?? ''));
	$view->setContent('LOG_ENTRY_DATE', esc(formatDateTimeIt((string)$log['created_at'])));
	$view->setContent('LOG_VISIT_DATE', esc(formatDateTimeIt((string)($log['appointment_at'] ?? ''))));
	$view->setContent('LOG_ENTRY_REASON', esc((string)($log['reason'] ?? 'Visita')));
	$view->setContent('LOG_ENTRY_NOTE', esc($noteText !== '' ? $noteText : 'Nessuna nota descritta.'));
}

$contentHtml = $view->get();

$base = new Template(__DIR__ . '/../../../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Storico pazienti');
$base->setContent('META_DESCRIPTION', 'Cronologia pazienti e aggiornamenti clinici per medici autorizzati.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Ciao, ' . esc((string)($user['full_name'] ?? $user['username'])));
populateBaseNavigation($base, (string)($user['role'] ?? 'doctor'), '../../logout.php', 'Esci');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));
$base->close();
