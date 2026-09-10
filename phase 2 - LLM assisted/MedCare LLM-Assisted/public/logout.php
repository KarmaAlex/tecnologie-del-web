<?php
/**
 * public/logout.php
 *
 * Distrugge la sessione e reindirizza al login. Accetta solo POST per
 * evitare logout via link/CSRF banale (GET) e verifica comunque il token
 * CSRF quando presente.
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/auth.php';

bootstrapSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    if (isLoggedIn()) {
        logAudit($_SESSION['user']['user_id'], 'logout', 'LOGOUT');
    }

    destroySession();
}

header('Location: /login.php');
exit;
