<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/template2.inc.php';
require_once __DIR__ . '/../include/functions.php';

bootstrapSession();

/**
 * Escape output bound to templates because template2 does not auto-escape.
 */
function esc(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function resolvePrimaryAction(string $role): array
{
	$role = strtolower($role);

	if ($role === 'patient') {
		return ['Prenota un appuntamento', 'services/patient/book_appointment.php'];
	}

	if ($role === 'doctor') {
		return ['Vedi storico pazienti', 'services/doctor/view_patient_history.php'];
	}

	if ($role === 'admin' || $role === 'staff') {
		return ['Gestisci dipartimenti', 'services/admin/crud_departments.php'];
	}

	return ['I nostri serivizi', '#services-overview'];
}

$user = $_SESSION['user'] ?? [];
$isLoggedIn = !empty($user);

$displayName = $isLoggedIn
	? (string)($user['full_name'] ?? $user['username'] ?? 'User')
	: 'Guest';

$role = $isLoggedIn ? (string)($user['role'] ?? 'patient') : '';
[$heroActionText, $heroActionUrl] = $isLoggedIn
	? resolvePrimaryAction($role)
	: ['Sign In', 'login.php'];

$secondaryActionText = 'I nostri servizi';
$secondaryActionUrl = '#services-overview';

$navActionText = $isLoggedIn ? $heroActionText : 'Login';
$navActionUrl = $isLoggedIn ? $heroActionUrl : 'login.php';
$navSecondaryActionText = $isLoggedIn ? 'Logout' : '';
$navSecondaryActionUrl = $isLoggedIn ? 'logout.php' : '';

$home = new Template(__DIR__ . '/../skins/frontend/home');
$home->setContent('WELCOME_BADGE', $isLoggedIn ? 'Bentornato' : 'Benvenuto a MedCare Portal');
$home->setContent('WELCOME_NAME', esc($displayName));
$home->setContent('HERO_TITLE', 'Cura digitale coordinata tra pazienti e medici');
$home->setContent('HERO_LEAD', 'Il nostro portale fornisce un punto di accesso sicuro per appuntamenti, cartelle cliniche e collaborazioni sanitarie.');
$home->setContent('HERO_ACTION_URL', $heroActionUrl);
$home->setContent('HERO_ACTION_TEXT', $heroActionText);
$home->setContent('HERO_SECONDARY_URL', $secondaryActionUrl);
$home->setContent('HERO_SECONDARY_TEXT', $secondaryActionText);
$home->setContent('HERO_ACTIONS_VISIBLE', $isLoggedIn ? '' : '1');

$home->setContent('PILLAR_ONE_TITLE', 'Accesso sicuro');
$home->setContent('PILLAR_ONE_BODY', 'Politiche di accesso basate sul ruolo, in modo che i tuoi dati siano visibili solo da te e dal tuo medico.');
$home->setContent('PILLAR_TWO_TITLE', 'Continuità clinica');
$home->setContent('PILLAR_TWO_BODY', 'Dottori e pazienti possono affidarsi ai nostri sistemi per tenere traccia di prescrizioni, appuntamenti e visite mediche passate');
$home->setContent('PILLAR_THREE_TITLE', 'Chiarezza operativa');
$home->setContent('PILLAR_THREE_BODY', "L'amministrazione gestisce i dipartimenti ed i turni dei medici, senza avere mai accesso a dati sensibili.");

$home->setContent('MISSION_TITLE', 'La nostra missione');
$home->setContent('MISSION_BODY', 'Offrire servizi sanitari digitali affidabili attraverso processi chiari, una gestione trasparente dei dati e una comunicazione professionale.');

$home->setContent('SERVICE_CARD_ONE_TITLE', 'Per i pazienti');
$home->setContent('SERVICE_CARD_ONE_BODY', 'Prenota appuntamenti e consulta le prescrizioni attraverso un processo semplice e pensato per le esigenze del paziente.');

$home->setContent('SERVICE_CARD_TWO_TITLE', 'Per il personale medico');
$home->setContent('SERVICE_CARD_TWO_BODY', 'Consulta la storia clinica dei pazienti e mantieni aggiornati i registri clinici in un ambiente controllato.');
$home->setContent('SERVICE_CARD_THREE_TITLE', 'Per gli amministratori');
$home->setContent('SERVICE_CARD_THREE_BODY', 'Organizza i reparti e gestisci gli orari per favorire un coordinamento efficace dell’assistenza.');

$home->setContent('CTA_TITLE', 'Hai bisogno di accedere alla tua area?');
$home->setContent('CTA_BODY', 'Utilizza le credenziali del tuo account per accedere agli strumenti e ai servizi dedicati al tuo ruolo.');
$home->setContent('CTA_URL', $isLoggedIn ? $heroActionUrl : 'login.php');
$home->setContent('CTA_TEXT', $isLoggedIn ? '' : 'Vai al Login');

$contentHtml = $home->get();

$base = new Template(__DIR__ . '/../skins/frontend/base');
$base->setContent('PAGE_TITLE', 'MedCare Portal - Professional Healthcare Access');
$base->setContent('META_DESCRIPTION', 'MedCare Portal is a professional healthcare platform for patients, medical staff, and administrators.');
$base->setContent('BRAND_NAME', 'MedCare Portal');
$base->setContent('NAV_WELCOME', $isLoggedIn ? 'Signed in as ' . esc($displayName) : 'Professional healthcare platform');
if ($isLoggedIn) {
	populateBaseNavigation($base, $role, 'logout.php', 'Sign Out');
}
$base->setContent('PAGE_CONTENT', $contentHtml);
$base->setContent('CURRENT_YEAR', date('Y'));

$base->close();
