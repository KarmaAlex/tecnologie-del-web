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
		return ['Book Appointment', 'services/patient/book_appointment.php'];
	}

	if ($role === 'doctor') {
		return ['View Patient History', 'services/doctor/view_patient_history.php'];
	}

	if ($role === 'admin' || $role === 'staff') {
		return ['Manage Departments', 'services/admin/crud_departments.php'];
	}

	return ['Browse Services', '#services-overview'];
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

$secondaryActionText = 'Find Care';
$secondaryActionUrl = '#services-overview';

$navActionText = $isLoggedIn ? $heroActionText : 'Sign In';
$navActionUrl = $isLoggedIn ? $heroActionUrl : 'login.php';
$navSecondaryActionText = $isLoggedIn ? 'Sign Out' : '';
$navSecondaryActionUrl = $isLoggedIn ? 'logout.php' : '';

$home = new Template(__DIR__ . '/../skins/frontend/home');
$home->setContent('WELCOME_BADGE', $isLoggedIn ? 'Welcome back' : 'Welcome to MedCare Portal');
$home->setContent('WELCOME_NAME', esc($displayName));
$home->setContent('HERO_TITLE', 'Coordinated Digital Care for Patients and Medical Teams');
$home->setContent('HERO_LEAD', 'MedCare Portal provides a secure and reliable point of access for appointments, clinical history, and healthcare collaboration.');
$home->setContent('HERO_ACTION_URL', $heroActionUrl);
$home->setContent('HERO_ACTION_TEXT', $heroActionText);
$home->setContent('HERO_SECONDARY_URL', $secondaryActionUrl);
$home->setContent('HERO_SECONDARY_TEXT', $secondaryActionText);
$home->setContent('HERO_ACTIONS_VISIBLE', $isLoggedIn ? '' : '1');

$home->setContent('PILLAR_ONE_TITLE', 'Secure Access');
$home->setContent('PILLAR_ONE_BODY', 'Role-aware access policies are designed around MedCare\'s users-groups-services model to protect every operation.');
$home->setContent('PILLAR_TWO_TITLE', 'Clinical Continuity');
$home->setContent('PILLAR_TWO_BODY', 'Doctors and patients can rely on structured records, appointment history, and coordinated updates through one portal.');
$home->setContent('PILLAR_THREE_TITLE', 'Operational Clarity');
$home->setContent('PILLAR_THREE_BODY', 'Administrative teams gain a consistent operational foundation to manage departments and scheduling activities.');

$home->setContent('MISSION_TITLE', 'Our Mission');
$home->setContent('MISSION_BODY', 'Deliver dependable digital healthcare workflows with clear responsibilities, transparent data handling, and professional communication.');

$home->setContent('SERVICE_CARD_ONE_TITLE', 'For Patients');
$home->setContent('SERVICE_CARD_ONE_BODY', 'Book appointments and review prescriptions through a streamlined, patient-first workflow.');
$home->setContent('SERVICE_CARD_TWO_TITLE', 'For Medical Staff');
$home->setContent('SERVICE_CARD_TWO_BODY', 'Review patient history and maintain up-to-date clinical logs in a controlled environment.');
$home->setContent('SERVICE_CARD_THREE_TITLE', 'For Administrators');
$home->setContent('SERVICE_CARD_THREE_BODY', 'Organize departments and manage schedules to support coordinated care delivery.');

$home->setContent('CTA_TITLE', 'Need access to your area?');
$home->setContent('CTA_BODY', 'Use your account credentials to continue with role-specific tools and services.');
$home->setContent('CTA_URL', $isLoggedIn ? $heroActionUrl : 'login.php');
$home->setContent('CTA_TEXT', $isLoggedIn ? '' : 'Go to Login');

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
