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
$logout->setContent('LOGOUT_TITLE', 'Log out avvenuto con successo');
$logout->setContent(
	'LOGOUT_MESSAGE',
	$wasLoggedIn
		? 'La sessione di ' . esc($displayName) . ' è stata terminate.'
		: 'Nessuna sessiona attiva, verrai rediretto alla home page.'
);
$logout->setContent('PRIMARY_URL', 'index.php');
$logout->setContent('PRIMARY_TEXT', 'Torna alla home');
$logout->setContent('SECONDARY_URL', 'login.php');
$logout->setContent('SECONDARY_TEXT', 'Accedi nuovamente');

$contentHtml = $logout->get();

$base = new Template(__DIR__ . '/../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Logout');
$base->setContent('META_DESCRIPTION', 'Sei uscito dal portale MedCare.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', 'Sessione terminata');
$base->setContent('NAV_ACTION_URL', 'login.php');
$base->setContent('NAV_ACTION_TEXT', 'Accedi');
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));

$base->close();
