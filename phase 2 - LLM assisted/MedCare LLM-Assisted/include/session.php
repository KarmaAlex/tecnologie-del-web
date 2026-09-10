<?php
/**
 * include/session.php
 *
 * Bootstrap centralizzato della sessione PHP nativa (AGENTS.md 4.2).
 * Ogni script che tocca $_SESSION deve passare da qui PRIMA di leggerla o
 * scriverla, incluso template2.inc.php stesso (che legge
 * $_SESSION['user']['services'][...] dentro Template::parse()).
 *
 * Struttura prevista di $_SESSION['user'] dopo un login riuscito:
 *   [
 *     'user_id'    => int,
 *     'username'   => string,
 *     'name'       => string,   // first_name
 *     'surname'    => string,   // last_name
 *     'email'      => string,
 *     'groups'     => string[],               // es. ['Patient_Tier']
 *     'group_ids'  => int[],
 *     'services'   => array<string,bool>,      // basename(script) => true
 *     'csrf_token' => string,
 *   ]
 */

declare(strict_types=1);

function bootstrapSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Nome sessione dedicato per non collidere con altre app sullo stesso host
    session_name('MEDCARE_SESSID');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    session_set_cookie_params([
        'lifetime' => 0,             // cookie di sessione (scade alla chiusura del browser)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Rafforza l'ID di sessione: evita fixation via query string / URL rewriting
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_start();

    // Il template engine (template2.inc.php, Template::get()/close()) fa
    // foreach($_SESSION['user'] as ...) senza controllare che la chiave
    // esista: se un utente non loggato apre una pagina, PHP emette
    // "Undefined array key" + "foreach() argument must be of type
    // array|object, null given". Non possiamo modificare il motore
    // (file pre-distribuito), quindi garantiamo qui che
    // $_SESSION['user'] esista sempre come array — vuoto per un
    // visitatore anonimo, popolato dopo login.php. isLoggedIn() resta
    // corretta perché continua a controllare 'user_id', non la sola
    // presenza dell'array.
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        $_SESSION['user'] = [];
    }

    // Rigenera periodicamente l'ID (mitiga session fixation su sessioni lunghe)
    if (!isset($_SESSION['_last_regeneration'])) {
        $_SESSION['_last_regeneration'] = time();
    } elseif (time() - $_SESSION['_last_regeneration'] > 900) { // ogni 15 minuti
        session_regenerate_id(true);
        $_SESSION['_last_regeneration'] = time();
    }
}

/**
 * True se esiste un utente autenticato in sessione.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user']['user_id']);
}

/**
 * Distrugge completamente la sessione corrente (cookie incluso).
 */
function destroySession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
