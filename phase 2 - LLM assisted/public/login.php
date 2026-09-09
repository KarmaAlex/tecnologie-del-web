<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/template2.inc.php';

bootstrapSession();

function esc(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fetchUserByIdentity(PDO $pdo, string $identity): ?array
{
	$sql = 'SELECT u.id, u.username, u.password_hash, u.email, u.full_name, u.role, u.active
			FROM users u
			WHERE u.username = :identity_username OR u.email = :identity_email
			LIMIT 1';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':identity_username' => $identity,
		':identity_email' => $identity,
	]);
	$row = $stmt->fetch();

	return $row !== false ? $row : null;
}

function fetchUserGroups(PDO $pdo, int $userId): array
{
	$sql = 'SELECT g.name
			FROM groups g
			INNER JOIN users_has_groups ug ON ug.group_id = g.id
			WHERE ug.user_id = :user_id
			ORDER BY g.name ASC';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':user_id' => $userId]);

	return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function fetchUserServices(PDO $pdo, int $userId): array
{
	$sql = 'SELECT DISTINCT s.path
			FROM services s
			INNER JOIN services_has_groups shg ON shg.service_id = s.id
			INNER JOIN users_has_groups ug ON ug.group_id = shg.group_id
			WHERE ug.user_id = :user_id';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':user_id' => $userId]);

	$services = [];
	$rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
	foreach ($rows as $path) {
		$path = (string)$path;
		$services[$path] = true;
		$services[basename($path)] = true;
	}

	return $services;
}

function verifyPassword(string $plainPassword, string $storedHash): bool
{
	if ($storedHash !== '' && password_verify($plainPassword, $storedHash)) {
		return true;
	}

	// Legacy support for sample data where passwords are not hashed yet.
	$info = password_get_info($storedHash);
	if (($info['algo'] ?? null) === null || ($info['algo'] ?? 0) === 0) {
		return hash_equals($storedHash, $plainPassword);
	}

	return false;
}

if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
	header('Location: index.php');
	exit;
}

$identity = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$identity = trim((string)($_POST['identity'] ?? ''));
	$password = (string)($_POST['password'] ?? '');

	if ($identity === '' || $password === '') {
		$errorMessage = 'Please provide both username or email and password.';
	} else {
		try {
			$pdo = getPDO();
			$user = fetchUserByIdentity($pdo, $identity);

			if ($user === null || (int)$user['active'] !== 1) {
				$errorMessage = 'Invalid credentials.';
			} else {
				$storedHash = (string)$user['password_hash'];

				if (!verifyPassword($password, $storedHash)) {
					$errorMessage = 'Invalid credentials.';
				} else {
					$userId = (int)$user['id'];
					$groups = fetchUserGroups($pdo, $userId);
					$services = fetchUserServices($pdo, $userId);

					session_regenerate_id(true);

					$_SESSION['user'] = [
						'id' => $userId,
						'username' => (string)$user['username'],
						'email' => (string)$user['email'],
						'full_name' => (string)($user['full_name'] ?: $user['username']),
						'role' => (string)$user['role'],
						'groups' => $groups,
						'services' => $services,
					];

					session_write_close();

					header('Location: index.php');
					exit;
				}
			}
		} catch (Throwable $e) {
			error_log('Login error: ' . $e->getMessage());
			$errorMessage = 'Login is temporarily unavailable. Please try again later.';
		}
	}
}

$login = new Template(__DIR__ . '/../skins/frontend/login');
$login->setContent('FORM_TITLE', 'Sign In');
$login->setContent('FORM_LEAD', 'Access your MedCare Portal area with your professional or patient credentials.');
$login->setContent('IDENTITY_LABEL', 'Username or Email');
$login->setContent('IDENTITY_VALUE', esc($identity));
$login->setContent('PASSWORD_LABEL', 'Password');
$login->setContent('SUBMIT_TEXT', 'Sign In Securely');
$login->setContent('HELP_TEXT', 'Use your assigned account. Contact administration if you cannot access your profile.');
$login->setContent('HOME_URL', 'index.php');
$login->setContent('HOME_TEXT', 'Return to Homepage');
$login->setContent('ALERT_CLASS', $errorMessage === '' ? 'alert is-hidden' : 'alert alert-error');
$login->setContent('ALERT_MESSAGE', esc($errorMessage));

$contentHtml = $login->get();

$base = new Template(__DIR__ . '/../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Sign In');
$base->setContent('META_DESCRIPTION', 'Sign in to MedCare Portal to access role-specific healthcare services.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Secure authentication area');
$base->setContent('NAV_ACTION_URL', 'index.php');
$base->setContent('NAV_ACTION_TEXT', 'Back to Home');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));

$base->close();
