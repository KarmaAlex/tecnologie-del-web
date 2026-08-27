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
$userId = (int)($user['id'] ?? 0);
$patientProfileId = getPatientProfileId($pdo, $userId);

$formMessage = '';
$formMessageClass = 'alert is-hidden';
$reasonValue = '';

/**
 * Fetches upcoming (future) doctor schedules with doctor/department/specialization
 * labels, so the patient can pick an available slot.
 */
function fetchAvailableSchedules(PDO $pdo): array
{
	$sql = 'SELECT sc.id, sc.start_at, sc.end_at, sc.location,
				u.full_name AS doctor_name,
				d.name AS department_name,
				sp.name AS specialization_name
			FROM schedules sc
			INNER JOIN doctor_profiles dp ON dp.id = sc.doctor_id
			INNER JOIN users u ON u.id = dp.user_id
			LEFT JOIN departments d ON d.id = dp.department_id
			LEFT JOIN specializations sp ON sp.id = dp.specialization_id
			WHERE sc.start_at >= NOW()
			ORDER BY sc.start_at ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute();

	return $stmt->fetchAll();
}

/**
 * Fetches the patient's own appointments, most recent first.
 */
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

/**
 * Loads a single schedule row by id, or null if it does not exist.
 */
function fetchScheduleById(PDO $pdo, int $scheduleId): ?array
{
	$stmt = $pdo->prepare('SELECT id, doctor_id, start_at, end_at FROM schedules WHERE id = :id LIMIT 1');
	$stmt->execute([':id' => $scheduleId]);
	$row = $stmt->fetch();

	return $row !== false ? $row : null;
}

/**
 * Confirms an appointment belongs to the given patient before cancelling it.
 */
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

	if (!verifyCsrfToken($token)) {
		$formMessage = 'Sessione scaduta, riprova.';
		$formMessageClass = 'alert alert-error';
	} elseif ($patientProfileId === null) {
		$formMessage = 'Il tuo account non ha ancora un profilo paziente collegato. Contatta l\'amministrazione.';
		$formMessageClass = 'alert alert-error';
	} elseif ($action === 'book') {
		$scheduleId = (int)($_POST['schedule_id'] ?? 0);
		$reasonValue = trim((string)($_POST['reason'] ?? ''));

		if ($scheduleId <= 0 || $reasonValue === '') {
			$formMessage = 'Seleziona una disponibilità e indica il motivo della visita.';
			$formMessageClass = 'alert alert-error';
		} else {
			$schedule = fetchScheduleById($pdo, $scheduleId);

			if ($schedule === null || strtotime((string)$schedule['start_at']) < time()) {
				$formMessage = 'La disponibilità selezionata non è più valida.';
				$formMessageClass = 'alert alert-error';
			} else {
				try {
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

$availableSchedules = fetchAvailableSchedules($pdo);
$appointments = $patientProfileId !== null ? fetchPatientAppointments($pdo, $patientProfileId) : [];

$booking = new Template(__DIR__ . '/../../../skins/frontend/booking');
$booking->setContent('PAGE_HEADING', 'Prenota una visita');
$booking->setContent('PAGE_LEAD', 'Scegli una disponibilità tra quelle offerte dai nostri medici e gestisci le tue prenotazioni.');
$booking->setContent('FORM_MESSAGE', $formMessage);
$booking->setContent('FORM_MESSAGE_CLASS', $formMessageClass);
$booking->setContent('CAN_BOOK', $patientProfileId === null ? '1' : '');
$booking->setContent('CSRF_TOKEN', csrfToken());
$booking->setContent('REASON_VALUE', esc($reasonValue));

if (empty($availableSchedules)) {
	$booking->setContent('SLOT_ID', '');
	$booking->setContent('SLOT_LABEL', 'Nessuna disponibilità al momento');
	$booking->setContent('SLOT_SELECTED', 'disabled');
} else {
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
$base->setContent('NAV_ACTION_URL', '../../index.php');
$base->setContent('NAV_ACTION_TEXT', 'Home');
$base->setContent('NAV_SECONDARY_ACTION_URL', '../../logout.php');
$base->setContent('NAV_SECONDARY_ACTION_TEXT', 'Esci');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));

$base->close();
