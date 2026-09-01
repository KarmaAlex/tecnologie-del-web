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

/**
 * Fetches all prescriptions issued to the patient, most recent first, each
 * with its prescribing doctor and department.
 */
function fetchPrescriptions(PDO $pdo, int $patientProfileId): array
{
	$sql = 'SELECT p.id, p.issued_at, p.notes,
				u.full_name AS doctor_name,
				d.name AS department_name
			FROM prescriptions p
			LEFT JOIN doctor_profiles dp ON dp.id = p.doctor_id
			LEFT JOIN users u ON u.id = dp.user_id
			LEFT JOIN departments d ON d.id = dp.department_id
			WHERE p.patient_id = :patient_id
			ORDER BY p.issued_at DESC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':patient_id' => $patientProfileId]);

	return $stmt->fetchAll();
}

/**
 * Fetches the medication items belonging to a single prescription.
 */
function fetchPrescriptionItems(PDO $pdo, int $prescriptionId): array
{
	$sql = 'SELECT medication, dosage, instructions, quantity
			FROM prescription_items
			WHERE prescription_id = :prescription_id
			ORDER BY id ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':prescription_id' => $prescriptionId]);

	return $stmt->fetchAll();
}

$prescriptions = $patientProfileId !== null ? fetchPrescriptions($pdo, $patientProfileId) : [];

$view = new Template(__DIR__ . '/../../../skins/frontend/prescriptions');
$view->setContent('PAGE_STYLES', (string)file_get_contents(__DIR__ . '/../../../skins/frontend/prescriptions.css'));
$view->setContent('PAGE_HEADING', 'Le tue prescrizioni');
$view->setContent('PAGE_LEAD', 'Consulta lo storico delle prescrizioni emesse dai medici che ti hanno seguito.');
$view->setContent('HAS_PRESCRIPTIONS', empty($prescriptions) ? '' : '1');

foreach ($prescriptions as $prescription) {
	$view->setContent('RX_DOCTOR', esc($prescription['doctor_name'] ?? 'Medico non specificato'));
	$view->setContent('RX_DEPARTMENT', esc($prescription['department_name'] ?? 'Reparto non specificato'));
	$view->setContent('RX_DATE', esc(formatDateTimeIt((string)$prescription['issued_at'])));

	$notes = trim((string)($prescription['notes'] ?? ''));
	$view->setContent('RX_NOTES_HTML', $notes !== '' ? '<p>' . esc($notes) . '</p>' : '');

	$items = fetchPrescriptionItems($pdo, (int)$prescription['id']);

	if (empty($items)) {
		$view->setContent('ITEM_MEDICATION', 'Nessun farmaco registrato');
		$view->setContent('ITEM_DOSAGE', '');
		$view->setContent('ITEM_QUANTITY', '');
		$view->setContent('ITEM_INSTRUCTIONS', '');
	} else {
		foreach ($items as $item) {
			$quantity = $item['quantity'] !== null ? $item['quantity'] . ' unità' : '';

			$view->setContent('ITEM_MEDICATION', esc((string)$item['medication']));
			$view->setContent('ITEM_DOSAGE', esc((string)($item['dosage'] ?? '')));
			$view->setContent('ITEM_QUANTITY', esc((string)$quantity));
			$view->setContent('ITEM_INSTRUCTIONS', esc((string)($item['instructions'] ?? '')));
		}
	}
}

$contentHtml = $view->get();

$base = new Template(__DIR__ . '/../../../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Le tue prescrizioni');
$base->setContent('META_DESCRIPTION', 'Consulta lo storico delle tue prescrizioni mediche su MedCare Portal.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Ciao, ' . esc((string)($user['full_name'] ?? $user['username'])));
populateBaseNavigation($base, (string)($user['role'] ?? 'patient'), '../../logout.php', 'Esci');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));

$base->close();
