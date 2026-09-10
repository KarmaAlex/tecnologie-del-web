<?php
/**
 * include/layout.php
 *
 * Header/footer condivisi per i controller dei verticali
 * (public/services/{patient,doctor,admin}/*.php). base.html resta il
 * layout della landing/dashboard (Fase 2); qui invece i singoli service
 * compongono la propria pagina come:
 *
 *   renderHeader($pageTitle);
 *   echo $tpl->get();   // corpo prodotto dal Template del verticale
 *   renderFooter();
 *
 * Niente logica di business qui: solo markup statico + i dati già
 * presenti in sessione (nome utente, gruppi). Le variabili vengono
 * escapate qui, non nei singoli controller, per avere un unico punto di
 * sanitizzazione dell'header.
 */

declare(strict_types=1);

function renderHeader(string $pageTitle, string $area = 'frontend'): void
{
    $user = $_SESSION['user'] ?? null;
    $title = htmlspecialchars($pageTitle, ENT_QUOTES);
    $fullName = $user ? htmlspecialchars($user['name'] . ' ' . $user['surname'], ENT_QUOTES) : '';
    $groups = $user ? htmlspecialchars(implode(', ', $user['groups']), ENT_QUOTES) : '';
    $formCsrf = csrfField();
    $csrfMeta = htmlspecialchars(csrfToken(), ENT_QUOTES);

    // 'frontend' -> paziente/medico (frontend.css), 'backend' -> admin
    // (backend.css). Stessi tokens.css sotto entrambi, vedi
    // public/assets/css/tokens.css.
    $stylesheet = $area === 'backend' ? 'backend.css' : 'frontend.css';

    echo <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} — MedCare Portal</title>
    <meta name="csrf-token" content="{$csrfMeta}">
    <link rel="stylesheet" href="/assets/css/{$stylesheet}">
</head>
<body>
    <header class="site-header">
        <a href="/index.php" class="brand">MedCare Portal</a>
        <nav class="topnav">
            <span class="topnav-user">{$fullName} <small>({$groups})</small></span>
            <form method="post" action="/logout.php" class="inline-form">
                {$formCsrf}
                <button type="submit" class="link-button">Logout</button>
            </form>
        </nav>
    </header>
    <main class="vertical-main">
HTML;
}

function renderFooter(): void
{
    echo <<<HTML
    </main>
    <footer class="site-footer">
        <p>MedCare Portal — Progetto accademico, Fase 4 (Design system).</p>
    </footer>
    <script src="/assets/js/main.js"></script>
</body>
</html>
HTML;
}
