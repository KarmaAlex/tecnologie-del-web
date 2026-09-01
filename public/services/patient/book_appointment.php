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
$userId = (int)($user['id'] ?? 0);
$patientProfileId = getPatientProfileId($pdo, $userId);

$formMessage = '';
$formMessageClass = 'alert is-hidden';
$reasonValue = '';
$selectedDepartmentId = (int)($_POST['department_id'] ?? $_GET['department_id'] ?? 0);
$selectedDoctorId = (int)($_POST['doctor_id'] ?? $_GET['doctor_id'] ?? 0);

function fetchDepartments(PDO $pdo): array
{
	$stmt = $pdo->prepare('SELECT id, name FROM departments ORDER BY name ASC');
	$stmt->execute();
	return $stmt->fetchAll();
}

function fetchDoctorsByDepartment(PDO $pdo, int $departmentId): array
{
	$stmt = $pdo->prepare('SELECT dp.id, u.full_name, d.name AS department_name, sp.name AS specialization_name
			FROM doctor_profiles dp
			INNER JOIN users u ON u.id = dp.user_id
			LEFT JOIN departments d ON d.id = dp.department_id
			LEFT JOIN specializations sp ON sp.id = dp.specialization_id
			WHERE dp.department_id = :department_id
			ORDER BY u.full_name ASC');
	$stmt->execute([':department_id' => $departmentId]);
	return $stmt->fetchAll();
}

function fetchAvailableSchedules(PDO $pdo, ?int $departmentId = null, ?int $doctorId = null): array
{
	$sql = 'SELECT sc.id, sc.doctor_id, sc.start_at, sc.end_at, sc.location,
				u.full_name AS doctor_name,
				d.name AS department_name,
				sp.name AS specialization_name
			FROM schedules sc
			INNER JOIN doctor_profiles dp ON dp.id = sc.doctor_id
			INNER JOIN users u ON u.id = dp.user_id
			LEFT JOIN departments d ON d.id = dp.department_id
			LEFT JOIN specializations sp ON sp.id = dp.specialization_id
			WHERE sc.start_at >= NOW()';

	$params = [];
	if ($departmentId !== null && $departmentId > 0) {
		$sql .= ' AND dp.department_id = :department_id';
		$params[':department_id'] = $departmentId;
	}
	if ($doctorId !== null && $doctorId > 0) {
		$sql .= ' AND dp.id = :doctor_id';
		$params[':doctor_id'] = $doctorId;
	}

	$sql .= ' ORDER BY sc.start_at ASC';
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll();
}

function fetchPatientAppointments(PDO $pdo, int $patientProfileId): array
{
	$sql = 'SELECT a.id, a.appointment_at, a.status,
				u.full_name AS doctor_name,
				d.name AS department_name
			FROM appointments a
			LEFT JOIN doctor_profiles dp ON dp.id = a.doctor_id
			LEFT JOIN users u ON u.id = dp.user_id
			LEFT JOIN departments d ON d.id = dp.department_id
			WHERE a.patient_id = :patient_id
			ORDER BY a.appointment_at DESC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':patient_id' => $patientProfileId]);
	return $stmt->fetchAll();
}

function fetchScheduleById(PDO $pdo, int $scheduleId): ?array
{
	$stmt = $pdo->prepare('SELECT id, doctor_id, start_at, end_at FROM schedules WHERE id = :id LIMIT 1');
	$stmt->execute([':id' => $scheduleId]);
	$row = $stmt->fetch();
	return $row !== false ? $row : null;
}

function cancelAppointment(PDO $pdo, int $appointmentId, int $patientProfileId): bool
{
	$sql = "UPDATE appointments
			SET status = 'cancelled'
			WHERE id = :id AND patient_id = :patient_id AND status IN ('booked','confirmed')";

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':id' => $appointmentId, ':patient_id' => $patientProfileId]);
	return $stmt->rowCount() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = (string)($_POST['csrf_token'] ?? '');
	$action = (string)($_POST['action'] ?? '');
	$selectedDepartmentId = (int)($_POST['department_id'] ?? 0);
	$selectedDoctorId = (int)($_POST['doctor_id'] ?? 0);

	if (!verifyCsrfToken($token)) {
		$formMessage = 'Sessione scaduta, riprova.';
		$formMessageClass = 'alert alert-error';
	} elseif ($patientProfileId === null) {
		$formMessage = 'Il tuo account non ha ancora un profilo paziente collegato. Contatta l\'amministrazione.';
		$formMessageClass = 'alert alert-error';
	} elseif ($action === 'book') {
		$scheduleId = (int)($_POST['schedule_id'] ?? 0);
		$reasonValue = trim((string)($_POST['reason'] ?? ''));

		if ($selectedDepartmentId <= 0 || $selectedDoctorId <= 0 || $scheduleId <= 0 || $reasonValue === '') {
			$formMessage = 'Seleziona reparto, medico, disponibilità e motivo della visita.';
			$formMessageClass = 'alert alert-error';
		} else {
			$schedule = fetchScheduleById($pdo, $scheduleId);
			if ($schedule === null || (int)$schedule['doctor_id'] !== $selectedDoctorId || strtotime((string)$schedule['start_at']) < time()) {
				$formMessage = 'La disponibilità selezionata non è più valida per il medico scelto.';
				$formMessageClass = 'alert alert-error';
			} else {
				try {
					$existing = $pdo->prepare('SELECT id FROM appointments WHERE patient_id = :patient_id AND schedule_id = :schedule_id AND status IN (\'booked\',\'confirmed\') LIMIT 1');
					$existing->execute([
						':patient_id' => $patientProfileId,
						':schedule_id' => $scheduleId,
					]);
					if ($existing->fetch() !== false) {
						$formMessage = 'Hai già prenotato questa disponibilità.';
						$formMessageClass = 'alert alert-error';
					} else {
						$insert = $pdo->prepare(
							'INSERT INTO appointments (patient_id, doctor_id, schedule_id, appointment_at, status, reason)
							 VALUES (:patient_id, :doctor_id, :schedule_id, :appointment_at, \'booked\', :reason)'
						);
						$insert->execute([
							':patient_id' => $patientProfileId,
							':doctor_id' => $schedule['doctor_id'],
							':schedule_id' => $schedule['id'],
							':appointment_at' => $schedule['start_at'],
							':reason' => $reasonValue,
						]);
						$formMessage = 'Prenotazione confermata con successo.';
						$formMessageClass = 'alert alert-success';
						$reasonValue = '';
					}
				} catch (Throwable $e) {
					error_log('Booking error: ' . $e->getMessage());
					$formMessage = 'Non è stato possibile completare la prenotazione. Riprova più tardi.';
					$formMessageClass = 'alert alert-error';
				}
			}
		}
	} elseif ($action === 'cancel') {
		$appointmentId = (int)($_POST['appointment_id'] ?? 0);
		if ($patientProfileId !== null && $appointmentId > 0 && cancelAppointment($pdo, $appointmentId, $patientProfileId)) {
			$formMessage = 'Prenotazione annullata.';
			$formMessageClass = 'alert alert-success';
		} else {
			$formMessage = 'Impossibile annullare questa prenotazione.';
			$formMessageClass = 'alert alert-error';
		}
	}
}

$departments = fetchDepartments($pdo);
$doctors = $selectedDepartmentId > 0 ? fetchDoctorsByDepartment($pdo, $selectedDepartmentId) : [];
$availableSchedules = fetchAvailableSchedules($pdo, $selectedDepartmentId > 0 ? $selectedDepartmentId : null, $selectedDoctorId > 0 ? $selectedDoctorId : null);
$appointments = $patientProfileId !== null ? fetchPatientAppointments($pdo, $patientProfileId) : [];

$booking = new Template(__DIR__ . '/../../../skins/frontend/booking');
$booking->setContent('PAGE_STYLES', (string)file_get_contents(__DIR__ . '/../../../skins/frontend/booking.css'));
$booking->setContent('PAGE_HEADING', 'Prenota una visita');
$booking->setContent('PAGE_LEAD', 'Scegli reparto, medico e fascia oraria disponibili per confermare la visita.');
$booking->setContent('FORM_MESSAGE', $formMessage);
$booking->setContent('FORM_MESSAGE_CLASS', $formMessageClass);
$booking->setContent('CAN_BOOK', $patientProfileId === null ? '1' : '');
$booking->setContent('CSRF_TOKEN', csrfToken());
$booking->setContent('REASON_VALUE', esc($reasonValue));
$booking->setContent('SELECTED_DEPARTMENT_ID', (string)$selectedDepartmentId);
$booking->setContent('SELECTED_DOCTOR_ID', (string)$selectedDoctorId);
$booking->setContent('HAS_DEPARTMENTS', empty($departments) ? '' : '1');
$booking->setContent('HAS_DOCTORS', empty($doctors) ? '' : '1');
$booking->setContent('HAS_SLOTS', empty($availableSchedules) ? '' : '1');

foreach ($departments as $department) {
	$selected = ((int)$department['id'] === $selectedDepartmentId) ? 'selected' : '';
	$booking->setContent('DEPT_OPTION_ID', (string)$department['id']);
	$booking->setContent('DEPT_OPTION_LABEL', esc((string)$department['name']));
	$booking->setContent('DEPT_OPTION_SELECTED', $selected);
}

foreach ($doctors as $doctor) {
	$selected = ((int)$doctor['id'] === $selectedDoctorId) ? 'selected' : '';
	$booking->setContent('DOCTOR_OPTION_ID', (string)$doctor['id']);
	$booking->setContent('DOCTOR_OPTION_LABEL', esc((string)$doctor['full_name'] . ' - ' . ($doctor['specialization_name'] ?? 'Generale')));
	$booking->setContent('DOCTOR_OPTION_SELECTED', $selected);
}

if (!empty($availableSchedules)) {
	foreach ($availableSchedules as $slot) {
		$label = sprintf(
			'%s - Dr. %s (%s)%s',
			formatDateTimeIt((string)$slot['start_at']),
			$slot['doctor_name'],
			$slot['department_name'] ?? $slot['specialization_name'] ?? 'Ambulatorio generico',
			!empty($slot['location']) ? ' - ' . $slot['location'] : ''
		);
		$booking->setContent('SLOT_ID', (string)$slot['id']);
		$booking->setContent('SLOT_LABEL', esc($label));
		$booking->setContent('SLOT_SELECTED', '');
	}
}

$booking->setContent('HAS_APPOINTMENTS', empty($appointments) ? '' : '1');
foreach ($appointments as $appt) {
	[$statusLabel, $statusClass] = appointmentStatusBadge((string)$appt['status']);
	$canCancel = in_array($appt['status'], ['booked', 'confirmed'], true);
	$booking->setContent('APPT_DATE', esc(formatDateTimeIt((string)$appt['appointment_at'])));
	$booking->setContent('APPT_DOCTOR', esc($appt['doctor_name'] ?? 'N/D'));
	$booking->setContent('APPT_DEPARTMENT', esc($appt['department_name'] ?? 'N/D'));
	$booking->setContent('APPT_STATUS_LABEL', esc($statusLabel));
	$booking->setContent('APPT_STATUS_CLASS', $statusClass);
	$cancelHtml = '';
	if ($canCancel) {
		$cancelHtml = '<form method="post" action="book_appointment.php" onsubmit="return confirm(\'Annullare questa prenotazione?\');">'
			. '<input type="hidden" name="csrf_token" value="' . esc(csrfToken()) . '">'
			. '<input type="hidden" name="action" value="cancel">'
			. '<input type="hidden" name="appointment_id" value="' . (int)$appt['id'] . '">'
			. '<button type="submit" class="btn btn-danger-outline btn-small">Annulla</button>'
			. '</form>';
	}
	$booking->setContent('APPT_CANCEL_HTML', $cancelHtml);
}

$contentHtml = $booking->get();

$base = new Template(__DIR__ . '/../../../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Prenota una visita');
$base->setContent('META_DESCRIPTION', 'Prenota una visita medica e consulta le tue prenotazioni su MedCare Portal.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Ciao, ' . esc((string)($user['full_name'] ?? $user['username'])));
populateBaseNavigation($base, (string)($user['role'] ?? 'patient'), '../../logout.php', 'Esci');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));

$base->close();
