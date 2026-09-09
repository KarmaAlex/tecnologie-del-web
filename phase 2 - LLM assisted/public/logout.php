<?php
declare(strict_types=1);

require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/template2.inc.php';

bootstrapSession();

function esc(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$displayName = (string)($_SESSION['user']['full_name'] ?? $_SESSION['user']['username'] ?? 'User');
$wasLoggedIn = isset($_SESSION['user']) && is_array($_SESSION['user']);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(
		session_name(),
		'',
		time() - 42000,
		$params['path'],
		$params['domain'],
		(bool)$params['secure'],
		(bool)$params['httponly']
	);
}

session_destroy();

$logout = new Template(__DIR__ . '/../skins/frontend/logout');
$logout->setContent('LOGOUT_TITLE', 'Signed Out Successfully');
$logout->setContent(
	'LOGOUT_MESSAGE',
	$wasLoggedIn
		? 'The session for ' . esc($displayName) . ' has been closed securely.'
		: 'No active session was detected, but you can continue to the homepage.'
);
$logout->setContent('PRIMARY_URL', 'index.php');
$logout->setContent('PRIMARY_TEXT', 'Return to Homepage');
$logout->setContent('SECONDARY_URL', 'login.php');
$logout->setContent('SECONDARY_TEXT', 'Sign In Again');

$contentHtml = $logout->get();

$base = new Template(__DIR__ . '/../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Signed Out');
$base->setContent('META_DESCRIPTION', 'You have signed out from MedCare Portal.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Session closed');
$base->setContent('NAV_ACTION_URL', 'login.php');
$base->setContent('NAV_ACTION_TEXT', 'Sign In');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));

$base->close();
