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
