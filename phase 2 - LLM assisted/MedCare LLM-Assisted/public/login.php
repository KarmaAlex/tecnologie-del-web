<?php
/**
 * public/login.php
 *
 * Security access processor (AGENTS.md target directory blueprint).
 * Al login riuscito risolve:
 *   users -> users_has_groups -> groups -> services_has_groups -> services
 * e precarica in sessione sia i group_ids (usati da requireService() per
 * la query di autorizzazione) sia l'elenco dei service_name concessi
 * (utile agli skin per mostrare/nascondere voci di menu).
 */

declare(strict_types=1);

require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/template2.inc.php';

bootstrapSession();

// Se già loggato, non ha senso ri-mostrare il form.
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$redirectTarget = $_GET['redirect'] ?? $_POST['redirect'] ?? '/index.php';
// Evita open-redirect: accetta solo path locali che iniziano con "/"
if (!is_string($redirectTarget) || !str_starts_with($redirectTarget, '/') || str_starts_with($redirectTarget, '//')) {
    $redirectTarget = '/index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Inserisci username e password.';
    } else {
        $pdo = getPDO();

        $stmt = $pdo->prepare(
            'SELECT user_id, username, password_hash, first_name, last_name, email, is_active
             FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if (!$row || !$row['is_active'] || !password_verify($password, $row['password_hash'])) {
            // Messaggio identico per utente inesistente/password errata: non
            // rivelare quale dei due campi era sbagliato (user enumeration).
            $errors[] = 'Credenziali non valide.';
            logAudit($row['user_id'] ?? null, 'login', 'LOGIN_FAIL');
        } else {

            // Rigenera l'ID di sessione ad ogni login (mitiga session fixation)
            session_regenerate_id(true);

            // Risolve i gruppi dell'utente
            $groupStmt = $pdo->prepare(
                'SELECT g.group_id, g.group_name
                 FROM groups g
                 JOIN users_has_groups uhg ON uhg.group_id = g.group_id
                 WHERE uhg.user_id = ?'
            );
            $groupStmt->execute([$row['user_id']]);
            $groupRows = $groupStmt->fetchAll();

            $groupIds = array_map(static fn($g) => (int)$g['group_id'], $groupRows);
            $groupNames = array_map(static fn($g) => $g['group_name'], $groupRows);

            // Risolve i service_name (+ execution_path, per costruire i
            // link nel menu) concessi a quei gruppi.
            $grantedServices = [];
            if (!empty($groupIds)) {
                $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
                $svcStmt = $pdo->prepare(
                    "SELECT DISTINCT s.service_name, s.execution_path
                     FROM services s
                     JOIN services_has_groups shg ON shg.service_id = s.service_id
                     WHERE shg.group_id IN ({$placeholders})
                     ORDER BY s.service_name"
                );
                $svcStmt->execute($groupIds);
                $grantedServices = $svcStmt->fetchAll();
            }

            $_SESSION['user'] = [
                'user_id'          => (int)$row['user_id'],
                'username'         => $row['username'],
                'name'             => $row['first_name'],
                'surname'          => $row['last_name'],
                'email'            => $row['email'],
                'groups'           => $groupNames,
                'group_ids'        => $groupIds,
                'granted_services' => $grantedServices, // [['service_name'=>..., 'execution_path'=>...], ...]
                'services'         => [],               // popolato da requireService() per-script
            ];

            logAudit((int)$row['user_id'], 'login', 'LOGIN_OK');

            header('Location: ' . $redirectTarget);
            exit;
        }
    }
}

$tpl = new Template(dirname(__DIR__) . '/skins/frontend/login');
$tpl->setContent('errors_html', $errors ? '<ul class="form-errors"><li>' . implode('</li><li>', array_map('htmlspecialchars', $errors)) . '</li></ul>' : '');
$tpl->setContent('csrf_field', csrfField());
$tpl->setContent('csrf_token', htmlspecialchars(csrfToken(), ENT_QUOTES));
$tpl->setContent('redirect', htmlspecialchars($redirectTarget, ENT_QUOTES));
$tpl->setContent('username_value', htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES));
$tpl->close();
