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
	$sql = 'SELECT id FROM doctor_profiles WHERE user_id = :user_id LIMIT 1';
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':user_id' => $userId]);
	$row = $stmt->fetch();

	return $row !== false ? (int)$row['id'] : null;
}

function fetchDoctorPatients(PDO $pdo, int $doctorId): array
{
	$sql = 'SELECT DISTINCT pp.id AS patient_profile_id,
				u.full_name AS patient_name
			FROM appointments a
			INNER JOIN patient_profiles pp ON pp.id = a.patient_id
			INNER JOIN users u ON u.id = pp.user_id
			WHERE a.doctor_id = :doctor_id
			ORDER BY u.full_name ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':doctor_id' => $doctorId]);

	return $stmt->fetchAll();
}

function fetchDoctorAppointments(PDO $pdo, int $doctorId, ?int $patientProfileId = null): array
{
	$sql = 'SELECT a.id AS appointment_id,
				pp.id AS patient_profile_id,
				u.full_name AS patient_name,
				a.appointment_at,
				a.status,
				a.reason,
				(SELECT ml.note FROM medical_logs ml WHERE ml.appointment_id = a.id ORDER BY ml.created_at DESC LIMIT 1) AS existing_note
			FROM appointments a
			INNER JOIN patient_profiles pp ON pp.id = a.patient_id
			INNER JOIN users u ON u.id = pp.user_id
			WHERE a.doctor_id = :doctor_id';

	$params = [':doctor_id' => $doctorId];
	if ($patientProfileId !== null && $patientProfileId > 0) {
		$sql .= ' AND pp.id = :patient_id';
		$params[':patient_id'] = $patientProfileId;
	}

	$sql .= ' ORDER BY a.appointment_at DESC';
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll();
}

function fetchRecentDoctorLogs(PDO $pdo, int $doctorId, ?int $patientProfileId = null): array
{
	$sql = 'SELECT ml.id, ml.note, ml.created_at, u.full_name AS patient_name,
				a.appointment_at, a.reason
			FROM medical_logs ml
			INNER JOIN patient_profiles pp ON pp.id = ml.patient_id
			INNER JOIN users u ON u.id = pp.user_id
			LEFT JOIN appointments a ON a.id = ml.appointment_id
			WHERE ml.doctor_id = :doctor_id';

	$params = [':doctor_id' => $doctorId];
	if ($patientProfileId !== null && $patientProfileId > 0) {
		$sql .= ' AND ml.patient_id = :patient_id';
		$params[':patient_id'] = $patientProfileId;
	}

	$sql .= ' ORDER BY ml.created_at DESC LIMIT 8';
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll();
}

$doctorProfileId = fetchDoctorProfileId($pdo, (int)($user['id'] ?? 0));
if ($doctorProfileId === null) {
	denyAccess();
}

$formMessage = '';
$formMessageClass = 'alert is-hidden';
$selectedPatientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$selectedAppointmentId = (int)($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
$noteValue = trim((string)($_POST['note'] ?? ''));

$patients = fetchDoctorPatients($pdo, $doctorProfileId);
if ($selectedPatientId <= 0 && !empty($patients)) {
	$selectedPatientId = (int)$patients[0]['patient_profile_id'];
}

$appointments = fetchDoctorAppointments($pdo, $doctorProfileId, $selectedPatientId > 0 ? $selectedPatientId : null);
if ($selectedAppointmentId <= 0 && !empty($appointments)) {
	$selectedAppointmentId = (int)$appointments[0]['appointment_id'];
}

$recentLogs = fetchRecentDoctorLogs($pdo, $doctorProfileId, $selectedPatientId > 0 ? $selectedPatientId : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = (string)($_POST['csrf_token'] ?? '');
	$action = (string)($_POST['action'] ?? '');
	$selectedPatientId = (int)($_POST['patient_id'] ?? 0);
	$selectedAppointmentId = (int)($_POST['appointment_id'] ?? 0);
	$noteValue = trim((string)($_POST['note'] ?? ''));

	if (!verifyCsrfToken($token)) {
		$formMessage = 'Sessione scaduta. Riprova.';
		$formMessageClass = 'alert alert-error';
	} elseif ($action !== 'save') {
		$formMessage = 'Azione non valida.';
		$formMessageClass = 'alert alert-error';
	} elseif ($selectedPatientId <= 0 || $selectedAppointmentId <= 0 || $noteValue === '') {
		$formMessage = 'Seleziona un paziente, una visita e inserisci una nota clinica.';
		$formMessageClass = 'alert alert-error';
	} else {
		$appointmentCheck = $pdo->prepare('SELECT id FROM appointments WHERE id = :appointment_id AND doctor_id = :doctor_id AND patient_id = :patient_id LIMIT 1');
		$appointmentCheck->execute([
			':appointment_id' => $selectedAppointmentId,
			':doctor_id' => $doctorProfileId,
			':patient_id' => $selectedPatientId,
		]);
		$appointmentRow = $appointmentCheck->fetch();

		if ($appointmentRow === false) {
			$formMessage = 'La visita selezionata non è valida per il tuo profilo medico.';
			$formMessageClass = 'alert alert-error';
		} else {
			try {
				$existingLog = $pdo->prepare('SELECT id FROM medical_logs WHERE appointment_id = :appointment_id AND doctor_id = :doctor_id LIMIT 1');
				$existingLog->execute([
					':appointment_id' => $selectedAppointmentId,
					':doctor_id' => $doctorProfileId,
				]);
				$logRow = $existingLog->fetch();

				if ($logRow !== false) {
					$stmt = $pdo->prepare('UPDATE medical_logs SET note = :note, attachments = :attachments WHERE id = :id AND doctor_id = :doctor_id');
					$stmt->execute([
						':note' => $noteValue,
						':attachments' => '',
						':id' => (int)$logRow['id'],
						':doctor_id' => $doctorProfileId,
					]);
					$formMessage = 'Nota clinica aggiornata correttamente.';
				} else {
					$stmt = $pdo->prepare('INSERT INTO medical_logs (doctor_id, patient_id, appointment_id, note, attachments) VALUES (:doctor_id, :patient_id, :appointment_id, :note, :attachments)');
					$stmt->execute([
						':doctor_id' => $doctorProfileId,
						':patient_id' => $selectedPatientId,
						':appointment_id' => $selectedAppointmentId,
						':note' => $noteValue,
						':attachments' => '',
					]);
					$formMessage = 'Nota clinica salvata correttamente.';
				}
				$formMessageClass = 'alert alert-success';
				$noteValue = '';
				$appointments = fetchDoctorAppointments($pdo, $doctorProfileId, $selectedPatientId > 0 ? $selectedPatientId : null);
				$recentLogs = fetchRecentDoctorLogs($pdo, $doctorProfileId, $selectedPatientId > 0 ? $selectedPatientId : null);
			} catch (Throwable $e) {
				error_log('Medical log save error: ' . $e->getMessage());
				$formMessage = 'Non è stato possibile salvare la nota. Riprova più tardi.';
				$formMessageClass = 'alert alert-error';
			}
		}
	}
}

$view = new Template(__DIR__ . '/../../../skins/backend/medical_log');
$view->setContent('PAGE_STYLES', (string)file_get_contents(__DIR__ . '/../../../skins/backend/patient_log.css'));
$view->setContent('PAGE_HEADING', 'Aggiorna cartella clinica');
$view->setContent('PAGE_LEAD', 'Registra note cliniche e mantieni il contesto terapeutico dei pazienti assegnati.');
$view->setContent('FORM_MESSAGE', $formMessage);
$view->setContent('FORM_MESSAGE_CLASS', $formMessageClass);
$view->setContent('CSRF_TOKEN', csrfToken());
$view->setContent('NOTE_VALUE', esc($noteValue));
$view->setContent('SELECTED_PATIENT_ID', (string)$selectedPatientId);
$view->setContent('SELECTED_APPOINTMENT_ID', (string)$selectedAppointmentId);
$view->setContent('HAS_PATIENTS', empty($patients) ? '' : '1');
$view->setContent('HAS_APPOINTMENTS', empty($appointments) ? '' : '1');
$view->setContent('HAS_RECENT_LOGS', empty($recentLogs) ? '' : '1');

foreach ($patients as $patient) {
	$selected = ((int)$patient['patient_profile_id'] === $selectedPatientId) ? 'selected' : '';
	$view->setContent('PATIENT_OPTION_ID', (string)$patient['patient_profile_id']);
	$view->setContent('PATIENT_OPTION_NAME', esc((string)$patient['patient_name']));
	$view->setContent('PATIENT_OPTION_SELECTED', $selected);
}

foreach ($appointments as $appointment) {
	$selected = ((int)$appointment['appointment_id'] === $selectedAppointmentId) ? 'selected' : '';
	$view->setContent('APPOINTMENT_OPTION_ID', (string)$appointment['appointment_id']);
	$view->setContent('APPOINTMENT_OPTION_LABEL', esc(formatDateTimeIt((string)$appointment['appointment_at']) . ' - ' . ($appointment['reason'] ?? 'Visita')));
	$view->setContent('APPOINTMENT_OPTION_SELECTED', $selected);
}

foreach ($recentLogs as $log) {
	$view->setContent('LOG_ENTRY_DATE', esc(formatDateTimeIt((string)$log['created_at'])));
	$view->setContent('LOG_ENTRY_PATIENT', esc((string)($log['patient_name'] ?? 'Paziente')));
	$view->setContent('LOG_ENTRY_REASON', esc((string)($log['reason'] ?? 'Visita')));
	$view->setContent('LOG_ENTRY_NOTE', esc((string)($log['note'] ?? '')));
}

$contentHtml = $view->get();

$base = new Template(__DIR__ . '/../../../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Aggiorna cartella clinica');
$base->setContent('META_DESCRIPTION', 'Aggiorna le note cliniche dei pazienti assegnati e consulta i registri recenti.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Ciao, ' . esc((string)($user['full_name'] ?? $user['username'])));
populateBaseNavigation($base, (string)($user['role'] ?? 'doctor'), '../../logout.php', 'Esci');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));
$base->close();
