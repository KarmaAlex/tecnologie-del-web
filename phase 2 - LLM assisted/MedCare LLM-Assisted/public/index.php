<?php
/**
 * public/index.php
 *
 * Context router / landing controller (AGENTS.md target directory
 * blueprint):
 *   - mostra una landing pubblica se non loggati
 *   - mostra una dashboard con nome utente, gruppi e menu dei service
 *     concessi se loggati. Ogni service con un controller già
 *     implementato compare come link cliccabile verso
 *     /services/{ruolo}/{nome}.php; i service registrati in
 *     schema.sql ma non ancora scritti compaiono come testo
 *     disabilitato, per non proporre link 404.
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

    // Menu dinamico: una voce per ogni service concesso ai gruppi
    // dell'utente. Se il controller esiste davvero sotto public/, è un
    // link cliccabile; altrimenti (service registrato in schema.sql ma
    // non ancora implementato) resta testo disabilitato, per non
    // proporre link che risponderebbero 404.
    $menuItems = '';
    foreach ($user['granted_services'] as $service) {
        $name = htmlspecialchars($service['service_name'], ENT_QUOTES);
        $path = $service['execution_path'];
        $controllerExists = is_file(dirname(__DIR__) . '/public/' . $path);

        if ($controllerExists) {
            $href = htmlspecialchars('/' . $path, ENT_QUOTES);
            $menuItems .= '<li><a href="' . $href . '">' . $name . '</a></li>' . "\n";
        } else {
            $menuItems .= '<li><span class="muted">' . $name . ' (non ancora disponibile)</span></li>' . "\n";
        }
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
