<?php
/**
 * public/index.php
 *
 * Context router / landing controller (AGENTS.md target directory
 * blueprint). In questa fase non esistono ancora service concreti sotto
 * public/services/*, quindi il router si limita a:
 *   - mostrare una landing pubblica se non loggati
 *   - mostrare una dashboard minimale (nome utente + gruppi + menu dei
 *     service concessi) se loggati
 *
 * Ogni futuro service in public/services/{patient,doctor,admin}/*.php
 * aprirà con lo stesso identico preambolo:
 *
 *   require_once __DIR__ . '/../../../include/auth.php';
 *   require_once __DIR__ . '/../../../include/template2.inc.php';
 *   requireService('nome_service_registrato_in_db');
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/template2.inc.php';

bootstrapSession();
loadAuth();

$tpl = new Template(dirname(__DIR__) . '/skins/frontend/base');

$tpl->setContent('page_title', 'MedCare Portal');
$tpl->setContent('csrf_token', htmlspecialchars(csrfToken(), ENT_QUOTES));

if (isLoggedIn()) {
    $user = $_SESSION['user'];

    $fullName = htmlspecialchars($user['name'] . ' ' . $user['surname'], ENT_QUOTES);
    $groups = htmlspecialchars(implode(', ', $user['groups']), ENT_QUOTES);

    $tpl->setContent('is_logged_in', '1');
    $tpl->setContent('user_fullname', $fullName);
    $tpl->setContent('user_groups', $groups);

    // Menu dinamico: una voce <li> per ogni service concesso ai gruppi
    // dell'utente. In questa fase i service non sono ancora implementati:
    // il menu elenca solo i nomi registrati, senza link funzionanti.
    $menuItems = '';
    foreach ($user['granted_services'] as $serviceName) {
        $safe = htmlspecialchars($serviceName, ENT_QUOTES);
        $menuItems .= "<li>{$safe}</li>\n";
    }
    $menuItems = $menuItems !== '' ? $menuItems : '<li><em>Nessun service assegnato</em></li>';

    // IMPORTANTE: il valore passato a setContent() non deve contenere
    // altri placeholder <[ ]> — Template::parse() fa una sola passata di
    // sostituzione, quindi placeholder annidati nel valore di 'body' non
    // vengono mai risolti e finiscono ripuliti dalla regex finale di
    // get()/close(). Qui il markup va già completamente valorizzato.
    $tpl->setContent('body', '<section class="dashboard"><h1>Benvenuto, ' . $fullName . '</h1>'
        . '<p>Gruppi: ' . $groups . '</p>'
        . '<h2>Servizi disponibili</h2><ul>' . $menuItems . '</ul>'
        . '<form method="post" action="/logout.php">'
        . csrfField()
        . '<button type="submit">Logout</button>'
        . '</form></section>');
} else {
    $tpl->setContent('is_logged_in', '');
    $tpl->setContent('body', '<section class="landing"><h1>MedCare Portal</h1>'
        . '<p>Piattaforma clinica multi-specialistica.</p>'
        . '<p><a href="/login.php">Accedi</a></p></section>');
}

$tpl->close();
