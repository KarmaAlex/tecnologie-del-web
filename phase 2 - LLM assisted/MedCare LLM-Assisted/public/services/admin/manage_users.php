<?php
/**
 * public/services/admin/manage_users.php
 *
 * Service registrato come 'admin_manage_users'.
 * Crea nuovi utenti (username/email/nome/cognome/password) e permette
 * di attivare/disattivare un account esistente. L'assegnazione ai
 * gruppi RBAC è un service separato (admin_assign_groups) — un utente
 * appena creato qui non appartiene ancora a nessun gruppo: va assegnato
 * dopo, altrimenti requireService() negherà ogni accesso al suo primo
 * login (comportamento corretto e voluto, non un bug).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';
require_once __DIR__ . '/../../../include/badges.php';

requireService('admin_manage_users');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$feedback = null;
$feedbackType = 'info';

// -----------------------------------------------------------------
// POST: creazione utente o toggle attivo/disattivo
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $username = trim((string)($_POST['username'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $lastName = trim((string)($_POST['last_name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($username === '' || $email === '' || $firstName === '' || $lastName === '' || $password === '') {
                $feedback = 'Compila tutti i campi obbligatori.';
                $feedbackType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $feedback = 'Indirizzo email non valido.';
                $feedbackType = 'error';
            } elseif (mb_strlen($password) < 8) {
                $feedback = 'La password deve avere almeno 8 caratteri.';
                $feedbackType = 'error';
            } elseif (mb_strlen($username) > 50 || mb_strlen($email) > 120
                || mb_strlen($firstName) > 80 || mb_strlen($lastName) > 80) {
                $feedback = 'Uno o più campi superano la lunghezza massima consentita.';
                $feedbackType = 'error';
            } else {
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
                $checkStmt->execute([':username' => $username, ':email' => $email]);

                if ((int)$checkStmt->fetchColumn() > 0) {
                    $feedback = 'Username o email già registrati.';
                    $feedbackType = 'error';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare(
                        'INSERT INTO users (username, email, password_hash, first_name, last_name, is_active)
                         VALUES (:username, :email, :password_hash, :first_name, :last_name, 1)'
                    );
                    $stmt->execute([
                        ':username'      => $username,
                        ':email'         => $email,
                        ':password_hash' => $hash,
                        ':first_name'    => $firstName,
                        ':last_name'     => $lastName,
                    ]);

                    $feedback = 'Utente creato con successo. Ricorda di assegnargli un gruppo da "Assegnazione gruppi", altrimenti non potrà accedere a nessun servizio.';
                    $feedbackType = 'success';
                }
            }
        } elseif ($action === 'toggle_active') {
            $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

            if (!$targetUserId) {
                $feedback = 'Utente non valido.';
                $feedbackType = 'error';
            } elseif ($targetUserId === $userId) {
                // Un admin non deve poter disattivare se stesso: si
                // ritroverebbe fuori dal proprio account senza un altro
                // admin pronto a riattivarlo.
                $feedback = 'Non puoi disattivare il tuo stesso account.';
                $feedbackType = 'error';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE user_id = :user_id');
                $stmt->execute([':user_id' => $targetUserId]);
                $feedback = 'Stato account aggiornato.';
                $feedbackType = 'success';
            }
        } else {
            $feedback = 'Azione non riconosciuta.';
            $feedbackType = 'error';
        }

        logAudit($userId, 'admin_manage_users', 'ACCESS_GRANTED:' . $action);

    } catch (Throwable $e) {
        error_log('[manage_users] ' . $e->getMessage());
        $feedback = 'Si è verificato un errore. Riprova.';
        $feedbackType = 'error';
    }
}

// -----------------------------------------------------------------
// GET: elenco utenti con i gruppi assegnati
// -----------------------------------------------------------------
$stmt = $pdo->query(
    "SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.is_active,
            GROUP_CONCAT(g.group_name SEPARATOR ', ') AS group_names
     FROM users u
     LEFT JOIN users_has_groups uhg ON uhg.user_id = u.user_id
     LEFT JOIN groups g ON g.group_id = uhg.group_id
     GROUP BY u.user_id, u.username, u.email, u.first_name, u.last_name, u.is_active
     ORDER BY u.last_name, u.first_name"
);
$users = $stmt->fetchAll();

$userRows = '';
if (empty($users)) {
    $userRows = '<tr><td colspan="6"><em>Nessun utente registrato.</em></td></tr>';
} else {
    foreach ($users as $u) {
        $groupsLabel = $u['group_names']
            ? htmlspecialchars($u['group_names'], ENT_QUOTES)
            : '<span class="muted">Nessun gruppo</span>';

        $toggleLabel = $u['is_active'] ? 'Disattiva' : 'Riattiva';
        $isSelf = (int)$u['user_id'] === $userId;

        $toggleButton = $isSelf
            ? '<span class="muted">—</span>'
            : '<form method="post" action="/services/admin/manage_users.php" class="inline-form" data-confirm="Confermi il cambio di stato per questo account?">'
                . csrfField()
                . '<input type="hidden" name="action" value="toggle_active">'
                . '<input type="hidden" name="user_id" value="' . (int)$u['user_id'] . '">'
                . '<button type="submit">' . $toggleLabel . '</button>'
                . '</form>';

        $userRows .= '<tr>'
            . '<td>' . htmlspecialchars($u['last_name'] . ' ' . $u['first_name'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($u['username'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($u['email'], ENT_QUOTES) . '</td>'
            . '<td>' . $groupsLabel . '</td>'
            . '<td>' . renderUserStatusBadge((bool)$u['is_active']) . '</td>'
            . '<td>' . $toggleButton . '</td>'
            . '</tr>';
    }
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/backend/admin/manage_users');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('user_rows', $userRows);
$tpl->setContent('csrf_field', csrfField());

renderHeader('Gestione utenti', 'backend');
echo $tpl->get();
renderFooter();
