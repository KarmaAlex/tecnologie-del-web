<?php
/**
 * include/auth.php
 *
 * Gatekeeper RBAC (AGENTS.md 4.3): verifica users -> users_has_groups ->
 * groups -> services_has_groups -> services prima di eseguire qualunque
 * service in public/services/*.
 *
 * Contiene anche la classe Auth: il template engine (template2.inc.php)
 * decide il frame pubblico/privato con class_exists("Auth") dentro
 * Skin::resolve() — quindi la sua sola PRESENZA (non le sue istanze) è
 * significativa per il layout. Va inclusa solo quando l'utente è loggato:
 * vedi requireLogin()/loadAuth().
 *
 * Precondizione: bootstrapSession() deve essere già stata chiamata.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once dirname(__DIR__) . '/config/database.php';

/**
 * Marker class. La sua sola esistenza (class_exists) dice al template
 * engine di renderizzare frame-private.html invece di frame-public.html.
 * Viene definita on-demand da loadAuth() SOLO per richieste autenticate,
 * cosicché class_exists("Auth") rispecchi fedelmente lo stato di login
 * nello stesso ciclo di richiesta.
 */
if (!function_exists('loadAuth')) {
    function loadAuth(): void
    {
        if (class_exists('Auth', false)) {
            return; // già definita in questa request
        }

        if (!isLoggedIn()) {
            return; // resta assente -> frame pubblico
        }

        // eval-free class declaration trick: dichiarata dinamicamente solo qui
        eval('class Auth { 
            public static function user(): array { return $_SESSION["user"] ?? []; }
            public static function groups(): array { return $_SESSION["user"]["groups"] ?? []; }
        }');
    }
}

/**
 * Rediretta al login se non autenticato. Da chiamare come prima riga
 * utile (dopo i require) di qualunque script protetto.
 */
function requireLogin(): void
{
    bootstrapSession();
    loadAuth();

    if (!isLoggedIn()) {
        $returnTo = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?redirect=' . $returnTo);
        exit;
    }
}

/**
 * Gatekeeper vero e proprio: verifica che l'utente corrente appartenga a
 * un gruppo autorizzato per il service corrispondente allo script chiamante.
 *
 * $serviceName è il nome logico registrato in services.service_name
 * (es. 'patient_book_appointment'), NON il path fisico.
 *
 * Ogni volta che il controllo passa, popola anche
 * $_SESSION['user']['services'][basename(SCRIPT_NAME)] = true, perché
 * Template::parse() lo legge internamente per decidere se fare
 * l'html_entity_decode del buffer.
 */
function requireService(string $serviceName): void
{
    requireLogin();

    $userId = $_SESSION['user']['user_id'];
    $groupIds = $_SESSION['user']['group_ids'] ?? [];

    if (empty($groupIds)) {
        denyAccess("Nessun gruppo assegnato all'utente.");
    }

    static $cacheKey = null;
    static $cacheResult = null;

    // Micro-cache per request: requireService potrebbe essere chiamata più
    // volte nello stesso script (difensivo, non è previsto dal design).
    $key = $serviceName . ':' . $userId;
    if ($cacheKey === $key) {
        $authorized = $cacheResult;
    } else {
        $authorized = isServiceAuthorized($serviceName, $groupIds);
        $cacheKey = $key;
        $cacheResult = $authorized;
    }

    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if (!$authorized) {
        logAudit($userId, $serviceName, 'ACCESS_DENIED');
        denyAccess("Non sei autorizzato ad accedere a questa funzione.");
    }

    // Il template engine legge questa chiave in Template::parse()
    $_SESSION['user']['services'][$scriptName] = true;

    logAudit($userId, $serviceName, 'ACCESS_GRANTED');
}

/**
 * Query pura: l'utente (tramite i suoi group_ids) è autorizzato al service?
 */
function isServiceAuthorized(string $serviceName, array $groupIds): bool
{
    if (empty($groupIds)) {
        return false;
    }

    $pdo = getPDO();

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $sql = "SELECT COUNT(*) 
            FROM services s
            JOIN services_has_groups shg ON shg.service_id = s.service_id
            WHERE s.service_name = ? AND shg.group_id IN ({$placeholders})";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$serviceName], $groupIds));

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Termina la richiesta con 403 e un messaggio neutro (mai dettagli interni).
 */
function denyAccess(string $reason = 'Accesso negato.'): never
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8">'
        . '<title>Accesso negato</title></head><body>'
        . '<h1>403 - Accesso negato</h1><p>' . htmlspecialchars($reason, ENT_QUOTES) . '</p>'
        . '<p><a href="/index.php">Torna alla home</a></p>'
        . '</body></html>';
    exit;
}

/**
 * Scrive una riga in access_audit_log. Non deve mai interrompere il flusso
 * applicativo: eventuali errori di logging vengono silenziati (best-effort).
 */
function logAudit(?int $userId, string $serviceName, string $action): void
{
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "INSERT INTO access_audit_log (user_id, service_id, action, ip_address, occurred_at)
             SELECT ?, s.service_id, ?, ?, NOW()
             FROM services s WHERE s.service_name = ?
             UNION ALL
             SELECT ?, NULL, ?, ?, NOW()
             WHERE NOT EXISTS (SELECT 1 FROM services WHERE service_name = ?)
             LIMIT 1"
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->execute([$userId, $action, $ip, $serviceName, $userId, $action, $ip, $serviceName]);
    } catch (Throwable $e) {
        // Logging best-effort: non deve mai bloccare il gatekeeper.
        error_log('[audit] ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------

/**
 * Restituisce il token CSRF corrente, generandolo se assente.
 */
function csrfToken(): string
{
    bootstrapSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF ricevuto da form/AJAX (timing-safe).
 * Da chiamare in testa a ogni service che processa una richiesta POST.
 */
function verifyCsrfToken(?string $submittedToken): void
{
    bootstrapSession();

    $expected = $_SESSION['csrf_token'] ?? null;

    if (!$expected || !$submittedToken || !hash_equals($expected, $submittedToken)) {
        http_response_code(419);
        denyAccess('Token di sicurezza mancante o non valido. Ricarica la pagina e riprova.');
    }
}

/**
 * Helper per gli skin: emette un <input type="hidden"> con il token CSRF
 * corrente, pronto da inserire in ogni <form> che invia POST.
 */
function csrfField(): string
{
    $token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}
