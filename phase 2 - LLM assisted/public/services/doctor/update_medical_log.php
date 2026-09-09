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
$doctorProfileId = getDoctorProfileId($pdo, (int)($user['id'] ?? 0));

$formMessage = '';
$formMessageClass = 'is-hidden';

function fetchPatientById(PDO $pdo, int $patientId): ?array
{
	$sql = 'SELECT pp.id, u.full_name, pp.fiscal_code
			FROM patient_profiles pp
			INNER JOIN users u ON u.id = pp.user_id
			WHERE pp.id = :id
			LIMIT 1';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':id' => $patientId]);
	$row = $stmt->fetch();

	return $row !== false ? $row : null;
}

function fetchPatientMedicalLogs(PDO $pdo, int $patientId): array
{
	$sql = 'SELECT ml.note, ml.created_at, u.full_name AS doctor_name
			FROM medical_logs ml
			LEFT JOIN doctor_profiles dp ON dp.id = ml.doctor_id
			LEFT JOIN users u ON u.id = dp.user_id
			WHERE ml.patient_id = :patient_id
			ORDER BY ml.created_at DESC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':patient_id' => $patientId]);

	return $stmt->fetchAll();
}

$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = (string)($_POST['csrf_token'] ?? '');
	$note = trim((string)($_POST['note'] ?? ''));

	if (!verifyCsrfToken($token)) {
		$formMessage = 'Sessione scaduta, riprova.';
		$formMessageClass = 'bk-alert-error';
	} elseif ($doctorProfileId === null) {
		$formMessage = 'Il tuo account non ha un profilo medico collegato.';
		$formMessageClass = 'bk-alert-error';
	} elseif ($patientId <= 0 || $note === '') {
		$formMessage = 'Seleziona un paziente e scrivi una nota.';
		$formMessageClass = 'bk-alert-error';
	} else {
		try {
			$stmt = $pdo->prepare(
				'INSERT INTO medical_logs (doctor_id, patient_id, note) VALUES (:doctor_id, :patient_id, :note)'
			);
			$stmt->execute([
				':doctor_id' => $doctorProfileId,
				':patient_id' => $patientId,
				':note' => $note,
			]);
			$formMessage = 'Nota clinica salvata.';
			$formMessageClass = 'bk-alert-success';
		} catch (Throwable $e) {
			error_log('Medical log insert error: ' . $e->getMessage());
			$formMessage = 'Impossibile salvare la nota.';
			$formMessageClass = 'bk-alert-error';
		}
	}
}

$patient = $patientId > 0 ? fetchPatientById($pdo, $patientId) : null;
$selectedPatientHtml = '';

if ($patient !== null) {
	$logs = fetchPatientMedicalLogs($pdo, $patientId);

	$html = '<section class="bk-panel"><h2>' . esc((string)$patient['full_name']) . '</h2>'
		. '<p>Codice fiscale: ' . esc((string)$patient['fiscal_code']) . '</p>'
		. '<a class="bk-btn bk-btn-ghost bk-btn-small" href="view_patient_history.php?patient_id=' . (int)$patient['id'] . '">Torna alla scheda completa</a>'
		. '</section>';

	$html .= '<section class="bk-panel"><h2>Nuova nota clinica</h2>'
		. '<form class="bk-form-grid" method="post" action="update_medical_log.php">'
		. '<input type="hidden" name="csrf_token" value="' . esc(csrfToken()) . '">'
		. '<input type="hidden" name="patient_id" value="' . (int)$patient['id'] . '">'
		. '<div class="bk-field"><label for="note">Nota</label>'
		. '<textarea id="note" name="note" required maxlength="2000" placeholder="Osservazioni, esami richiesti, terapie..."></textarea></div>'
		. '<button type="submit" class="bk-btn bk-btn-primary">Salva nota</button>'
		. '</form></section>';

	$html .= '<section class="bk-panel"><h2>Note precedenti</h2>';
	if (empty($logs)) {
		$html .= '<p class="bk-empty-note">Nessuna nota clinica registrata.</p>';
	} else {
		foreach ($logs as $log) {
			$html .= '<div class="bk-history-card">'
				. '<div class="bk-history-meta">' . esc(formatDateTimeIt((string)$log['created_at'])) . ' &middot; Dr. ' . esc((string)($log['doctor_name'] ?? 'N/D')) . '</div>'
				. '<div>' . nl2br(esc((string)$log['note'])) . '</div>'
				. '</div>';
		}
	}
	$html .= '</section>';

	$selectedPatientHtml = $html;
}

$content = new Template(__DIR__ . '/../../../skins/backend/patient_log');
$content->setContent('SEARCH_QUERY', '');
$content->setContent('HAS_RESULTS', '');
$content->setContent('SEARCH_EMPTY_MESSAGE', $patient === null ? 'Apri prima una scheda paziente dallo Storico pazienti per aggiungere una nota.' : '');
$content->setContent('SELECTED_PATIENT_HTML', $selectedPatientHtml);

$contentHtml = $content->get();

$dashboard = new Template(__DIR__ . '/../../../skins/backend/dashboard');
$dashboard->setContent('PAGE_TITLE', 'MedCare Backoffice - Nota clinica');
$dashboard->setContent('PAGE_HEADING', 'Aggiungi nota clinica');
$dashboard->setContent('PAGE_LEAD', 'Registra osservazioni cliniche per un paziente selezionato.');
$dashboard->setContent('NAV_LINKS_HTML', renderBackendNav((string)$user['role'], 'update_medical_log.php'));
$dashboard->setContent('NAV_WELCOME', esc((string)($user['full_name'] ?? $user['username'])));
$dashboard->setContent('FORM_MESSAGE', esc($formMessage));
$dashboard->setContent('FORM_MESSAGE_CLASS', $formMessageClass);
$dashboard->setContent('PAGE_CONTENT', $contentHtml);

$dashboard->close();
