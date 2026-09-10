<?php
/**
 * public/services/patient/view_profile.php
 *
 * Service registrato come 'patient_view_profile'.
 * Sola lettura: mostra i dati anagrafici e assicurativi del paziente
 * loggato. La modifica dei dati assicurativi è un service separato
 * (patient_update_insurance) — qui non c'è alcun form di modifica,
 * solo un link verso quella pagina.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('patient_view_profile');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$stmt = $pdo->prepare(
    'SELECT u.username, u.email, u.first_name, u.last_name,
            p.date_of_birth, p.phone, p.address, p.insurance_provider, p.insurance_number
     FROM users u
     LEFT JOIN patients p ON p.user_id = u.user_id
     WHERE u.user_id = :user_id LIMIT 1'
);
$stmt->execute([':user_id' => $userId]);
$profile = $stmt->fetch();

$errorHtml = '';
$profileHtml = '';

if (!$profile) {
    // Non dovrebbe mai accadere (l'utente è loggato, quindi esiste in
    // users), ma niente assunzioni: un fetch() vuoto viene gestito
    // esplicitamente invece di proseguire su dati mancanti.
    $errorHtml = '<p class="feedback feedback-error">Impossibile caricare il profilo.</p>';
} else {
    $dob = $profile['date_of_birth'] ? date('d/m/Y', strtotime($profile['date_of_birth'])) : 'Non specificata';
    $phone = $profile['phone'] ?: 'Non specificato';
    $address = $profile['address'] ?: 'Non specificato';
    $insuranceProvider = $profile['insurance_provider'] ?: 'Non specificato';
    $insuranceNumber = $profile['insurance_number'] ?: 'Non specificato';

    $profileHtml = '<dl class="profile-details">'
        . '<dt>Nome completo</dt><dd>' . htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name'], ENT_QUOTES) . '</dd>'
        . '<dt>Username</dt><dd>' . htmlspecialchars($profile['username'], ENT_QUOTES) . '</dd>'
        . '<dt>Email</dt><dd>' . htmlspecialchars($profile['email'], ENT_QUOTES) . '</dd>'
        . '<dt>Data di nascita</dt><dd>' . htmlspecialchars($dob, ENT_QUOTES) . '</dd>'
        . '<dt>Telefono</dt><dd>' . htmlspecialchars($phone, ENT_QUOTES) . '</dd>'
        . '<dt>Indirizzo</dt><dd>' . htmlspecialchars($address, ENT_QUOTES) . '</dd>'
        . '<dt>Assicurazione</dt><dd>' . htmlspecialchars($insuranceProvider, ENT_QUOTES) . '</dd>'
        . '<dt>Numero polizza</dt><dd>' . htmlspecialchars($insuranceNumber, ENT_QUOTES) . '</dd>'
        . '</dl>';
}

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/patient/view_profile');
$tpl->setContent('error_html', $errorHtml);
$tpl->setContent('profile_html', $profileHtml);

renderHeader('Il mio profilo');
echo $tpl->get();
renderFooter();
