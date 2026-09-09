<?php
declare(strict_types=1);

/**
 * RBAC Gatekeeper (users -> groups -> services).
 *
 * Every operational script under public/services/* must call requireService()
 * before performing any task. login.php already resolves, at authentication
 * time, the full set of services a user is authorized to execute (through
 * their group memberships) and stores it in $_SESSION['user']['services']
 * keyed both by full relative path and by basename for convenience.
 */

/**
 * Ensures a user is authenticated. Redirects to the login page otherwise.
 * $loginPath is relative to the calling script's location.
 */
function requireLogin(string $loginPath = '../../login.php'): array
{
	if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
		header('Location: ' . $loginPath);
		exit;
	}

	return $_SESSION['user'];
}

/**
 * Ensures the logged-in user's groups grant access to the current service
 * script. This is the mandatory Gatekeeper check for every service endpoint.
 */
function requireService(string $loginPath = '../../login.php'): array
{
	$user = requireLogin($loginPath);

	$scriptName = basename($_SERVER['SCRIPT_NAME']);
	$authorizedServices = $user['services'] ?? [];

	if (!isset($authorizedServices[$scriptName])) {
		denyAccess();
	}

	return $user;
}

/**
 * Renders a minimal, dependency-free 403 page and stops execution.
 */
function denyAccess(): void
{
	http_response_code(403);
	echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">'
		. '<title>Accesso negato</title></head><body style="font-family:sans-serif;padding:3rem;">'
		. '<h1>403 - Accesso negato</h1>'
		. '<p>Il tuo account non appartiene a un gruppo autorizzato a eseguire questo servizio.</p>'
		. '<p><a href="../../index.php">Torna alla home</a></p>'
		. '</body></html>';
	exit;
}

/**
 * Returns the currently logged-in user's session data, or an empty array.
 */
function currentUser(): array
{
	return $_SESSION['user'] ?? [];
}

/**
 * CSRF protection helpers, used by every state-changing form.
 */
function csrfToken(): string
{
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
	return is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
