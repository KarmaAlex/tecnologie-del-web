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

/**
 * Searches patients by name or fiscal code.
 */
function searchPatients(PDO $pdo, string $query): array
{
	$like = '%' . $query . '%';
	$sql = 'SELECT pp.id, u.full_name, pp.fiscal_code, pp.dob
			FROM patient_profiles pp
			INNER JOIN users u ON u.id = pp.user_id
			WHERE u.full_name LIKE :q OR pp.fiscal_code LIKE :q
			ORDER BY u.full_name ASC
			LIMIT 30';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':q' => $like]);

	return $stmt->fetchAll();
}

/**
 * Loads a single patient's identity (name, fiscal code, dob).
 */
function fetchPatientById(PDO $pdo, int $patientId): ?array
{
	$sql = 'SELECT pp.id, u.full_name, pp.fiscal_code, pp.dob
			FROM patient_profiles pp
			INNER JOIN users u ON u.id = pp.user_id
			WHERE pp.id = :id
			LIMIT 1';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':id' => $patientId]);
	$row = $stmt->fetch();

	return $row !== false ? $row : null;
}

function fetchPatientAppointments(PDO $pdo, int $patientId): array
{
	$sql = 'SELECT a.appointment_at, a.status, a.reason, u.full_name AS doctor_name
			FROM appointments a
			LEFT JOIN doctor_profiles dp ON dp.id = a.doctor_id
			LEFT JOIN users u ON u.id = dp.user_id
			WHERE a.patient_id = :patient_id
			ORDER BY a.appointment_at DESC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':patient_id' => $patientId]);

	return $stmt->fetchAll();
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

$searchQuery = trim((string)($_GET['q'] ?? ''));
$results = $searchQuery !== '' ? searchPatients($pdo, $searchQuery) : [];

$selectedPatientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$selectedPatientHtml = '';

if ($selectedPatientId > 0) {
	$patient = fetchPatientById($pdo, $selectedPatientId);

	if ($patient !== null) {
		$appointments = fetchPatientAppointments($pdo, $selectedPatientId);
		$logs = fetchPatientMedicalLogs($pdo, $selectedPatientId);

		$dobFormatted = '';
		if (!empty($patient['dob'])) {
			$dobTs = strtotime((string)$patient['dob']);
			$dobFormatted = $dobTs !== false ? date('d/m/Y', $dobTs) : (string)$patient['dob'];
		}

		$html = '<section class="bk-panel"><h2>' . esc((string)$patient['full_name']) . '</h2>'
			. '<p>Codice fiscale: ' . esc((string)$patient['fiscal_code'])
			. ' &middot; Data di nascita: ' . esc($dobFormatted ?: 'N/D') . '</p>'
			. '<a class="bk-btn bk-btn-primary bk-btn-small" href="update_medical_log.php?patient_id=' . (int)$patient['id'] . '">Aggiungi nota clinica</a>'
			. '</section>';

		$html .= '<section class="bk-panel"><h2>Appuntamenti</h2>';
		if (empty($appointments)) {
			$html .= '<p class="bk-empty-note">Nessun appuntamento registrato.</p>';
		} else {
			$html .= '<div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Data</th><th>Medico</th><th>Stato</th><th>Motivo</th></tr></thead><tbody>';
			foreach ($appointments as $appt) {
				[$statusLabel, $statusClass] = appointmentStatusBadge((string)$appt['status']);
				$statusKey = str_replace('badge badge-status-', '', $statusClass);
				$html .= '<tr><td>' . esc(formatDateTimeIt((string)$appt['appointment_at'])) . '</td>'
					. '<td>' . esc((string)($appt['doctor_name'] ?? 'N/D')) . '</td>'
					. '<td><span class="bk-badge bk-badge-' . esc($statusKey) . '">' . esc($statusLabel) . '</span></td>'
					. '<td>' . esc((string)($appt['reason'] ?? '')) . '</td></tr>';
			}
			$html .= '</tbody></table></div>';
		}
		$html .= '</section>';

		$html .= '<section class="bk-panel"><h2>Note cliniche</h2>';
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
}

$content = new Template(__DIR__ . '/../../../skins/backend/patient_log');
$content->setContent('SEARCH_QUERY', esc($searchQuery));
$content->setContent('HAS_RESULTS', empty($results) ? '' : '1');
$content->setContent('SEARCH_EMPTY_MESSAGE', $searchQuery === '' ? 'Digita un nome o un codice fiscale per iniziare la ricerca.' : 'Nessun paziente trovato.');

foreach ($results as $res) {
	$viewHtml = '<a class="bk-btn bk-btn-ghost bk-btn-small" href="view_patient_history.php?patient_id=' . (int)$res['id'] . '&q=' . urlencode($searchQuery) . '">Apri scheda</a>';
	$resDobTs = !empty($res['dob']) ? strtotime((string)$res['dob']) : false;

	$content->setContent('RES_NAME', esc((string)$res['full_name']));
	$content->setContent('RES_FISCAL_CODE', esc((string)$res['fiscal_code']));
	$content->setContent('RES_DOB', esc($resDobTs !== false ? date('d/m/Y', $resDobTs) : ''));
	$content->setContent('RES_VIEW_HTML', $viewHtml);
}

$content->setContent('SELECTED_PATIENT_HTML', $selectedPatientHtml);

$contentHtml = $content->get();

$dashboard = new Template(__DIR__ . '/../../../skins/backend/dashboard');
$dashboard->setContent('PAGE_TITLE', 'MedCare Backoffice - Storico pazienti');
$dashboard->setContent('PAGE_HEADING', 'Storico pazienti');
$dashboard->setContent('PAGE_LEAD', 'Cerca un paziente per consultare appuntamenti e note cliniche.');
$dashboard->setContent('NAV_LINKS_HTML', renderBackendNav((string)$user['role'], 'view_patient_history.php'));
$dashboard->setContent('NAV_WELCOME', esc((string)($user['full_name'] ?? $user['username'])));
$dashboard->setContent('FORM_MESSAGE', '');
$dashboard->setContent('FORM_MESSAGE_CLASS', 'is-hidden');
$dashboard->setContent('PAGE_CONTENT', $contentHtml);

$dashboard->close();
