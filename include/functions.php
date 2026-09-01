<?php
declare(strict_types=1);

/**
 * Small, framework-free structural helpers shared by the public controllers.
 */

if (!function_exists('esc')) {
	function esc(?string $value): string
	{
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}
}

/**
 * Formats a MySQL DATETIME/TIMESTAMP string into an Italian-friendly
 * "gg/mm/aaaa hh:mm" representation. Returns an empty string on bad input.
 */
if (!function_exists('formatDateTimeIt')) {
	function formatDateTimeIt(?string $value): string
	{
		if ($value === null || $value === '') {
			return '';
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			return '';
		}

		return date('d/m/Y H:i', $timestamp);
	}
}

/**
 * Resolves the patient_profiles.id linked to a given users.id, or null if
 * the account has no patient profile yet.
 */
if (!function_exists('getPatientProfileId')) {
	function getPatientProfileId(PDO $pdo, int $userId): ?int
	{
		$stmt = $pdo->prepare('SELECT id FROM patient_profiles WHERE user_id = :user_id LIMIT 1');
		$stmt->execute([':user_id' => $userId]);
		$row = $stmt->fetch();

		return $row !== false ? (int)$row['id'] : null;
	}
}

/**
 * Builds a human readable status label + CSS badge modifier for an
 * appointment status value.
 */
if (!function_exists('appointmentStatusBadge')) {
	function appointmentStatusBadge(string $status): array
	{
		$labels = [
			'booked' => 'Prenotato',
			'confirmed' => 'Confermato',
			'completed' => 'Completato',
			'cancelled' => 'Annullato',
		];

		$label = $labels[$status] ?? ucfirst($status);

		return [$label, 'badge badge-status-' . preg_replace('~[^a-z]~', '', strtolower($status))];
	}
}

/**
 * Returns an array of navigation menu items available for a given user role.
 * Each item is an associative array with 'label' and 'url' keys.
 * Can be easily extended to add new pages by adding entries to the role arrays.
 */
if (!function_exists('getNavigationMenu')) {
	function getNavigationMenu(string $role): array
	{
		$role = strtolower(trim($role));

		$menuByRole = [
			'patient' => [
				['label' => 'Home', 'url' => '/index.php'],
				['label' => 'Book Appointment', 'url' => '/services/patient/book_appointment.php'],
				['label' => 'My Prescriptions', 'url' => '/services/patient/view_prescriptions.php'],
			],
			'doctor' => [
				['label' => 'Home', 'url' => '/index.php'],
				['label' => 'Patient History', 'url' => '/services/doctor/view_patient_history.php'],
				['label' => 'Update Medical Log', 'url' => '/services/doctor/update_medical_log.php'],
			],
			'admin' => [
				['label' => 'Home', 'url' => '/index.php'],
				['label' => 'Manage Departments', 'url' => '/services/admin/crud_departments.php'],
				['label' => 'Manage Schedules', 'url' => '/services/admin/manage_schedules.php'],
			]
		];

		return $menuByRole[$role] ?? [];
	}
}

/**
 * Populates a Template object with navigation menu items based on user role.
 * Handles both desktop and mobile navigation menu items.
 * Call this after creating the base template to set up all navigation.
 */
if (!function_exists('populateBaseNavigation')) {
	function populateBaseNavigation(Template $baseTemplate, string $userRole, string $logoutUrl, string $logoutText = 'Logout'): void
	{
		$menuItems = getNavigationMenu($userRole);

		if (!empty($menuItems)) {
			$baseTemplate->setContent('NAV_ITEMS', '1');

			foreach ($menuItems as $item) {
				$baseTemplate->setContent('NAV_ITEM_LABEL', esc($item['label']));
				$baseTemplate->setContent('NAV_ITEM_URL', esc($item['url']));
				$baseTemplate->setContent('NAV_ITEM_LABEL_MOBILE', esc($item['label']));
				$baseTemplate->setContent('NAV_ITEM_URL_MOBILE', esc($item['url']));
			}
		}

		if (!empty($logoutUrl)) {
			$baseTemplate->setContent('NAV_LOGOUT_URL', esc($logoutUrl));
			$baseTemplate->setContent('NAV_LOGOUT_TEXT', esc($logoutText));
			$baseTemplate->setContent('NAV_LOGOUT_URL_MOBILE', esc($logoutUrl));
			$baseTemplate->setContent('NAV_LOGOUT_TEXT_MOBILE', esc($logoutText));
		}
	}
}
