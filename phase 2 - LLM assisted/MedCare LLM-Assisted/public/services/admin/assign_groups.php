<?php
/**
 * public/services/admin/assign_groups.php
 *
 * Service registrato come 'admin_assign_groups'.
 * Assegna o rimuove un utente da un gruppo RBAC (users_has_groups).
 * Senza questo service, un utente creato da admin_manage_users.php
 * resta senza gruppi e requireService() gli nega ogni accesso.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';
require_once __DIR__ . '/../../../include/labels.php';

requireService('admin_assign_groups');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$feedback = null;
$feedbackType = 'info';

/**
 * Conta quanti utenti attivi appartengono al gruppo System_Admin,
 * diversi da $excludeUserId. Usata per impedire che l'ultimo admin
 * rimuova se stesso dal gruppo, il che bloccherebbe l'accesso a
 * questa stessa pagina per chiunque.
 */
function countOtherActiveAdmins(PDO $pdo, int $excludeUserId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM users u
         JOIN users_has_groups uhg ON uhg.user_id = u.user_id
         JOIN groups g ON g.group_id = uhg.group_id
         WHERE g.group_name = 'System_Admin' AND u.is_active = 1 AND u.user_id != :exclude_user_id"
    );
    $stmt->execute([':exclude_user_id' => $excludeUserId]);
    return (int)$stmt->fetchColumn();
}

// -----------------------------------------------------------------
// POST: assegnazione o rimozione
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';
    $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $groupId = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);

    if (!$targetUserId || !$groupId) {
        $feedback = 'Utente o gruppo non validi.';
        $feedbackType = 'error';
    } else {
        try {
            if ($action === 'assign') {
                $stmt = $pdo->prepare(
                    'INSERT IGNORE INTO users_has_groups (user_id, group_id) VALUES (:user_id, :group_id)'
                );
                $stmt->execute([':user_id' => $targetUserId, ':group_id' => $groupId]);
                $feedback = 'Gruppo assegnato con successo.';
                $feedbackType = 'success';
            } elseif ($action === 'revoke') {
                // Verifica di dominio: se il gruppo da rimuovere è
                // System_Admin e l'utente target è l'ultimo admin
                // attivo rimasto, blocca l'operazione — anche se il
                // richiedente è un admin diverso, altrimenti il sistema
                // resterebbe senza nessuno autorizzato a gestire gruppi.
                $groupStmt = $pdo->prepare('SELECT group_name FROM groups WHERE group_id = :group_id');
                $groupStmt->execute([':group_id' => $groupId]);
                $groupName = $groupStmt->fetchColumn();

                if ($groupName === 'System_Admin' && countOtherActiveAdmins($pdo, $targetUserId) === 0) {
                    $feedback = 'Impossibile rimuovere: è l\'ultimo amministratore attivo rimasto.';
                    $feedbackType = 'error';
                } else {
                    $stmt = $pdo->prepare(
                        'DELETE FROM users_has_groups WHERE user_id = :user_id AND group_id = :group_id'
                    );
                    $stmt->execute([':user_id' => $targetUserId, ':group_id' => $groupId]);
                    $feedback = 'Gruppo rimosso con successo.';
                    $feedbackType = 'success';
                }
            } else {
                $feedback = 'Azione non riconosciuta.';
                $feedbackType = 'error';
            }

            logAudit($userId, 'admin_assign_groups', 'ACCESS_GRANTED:' . $action);

        } catch (Throwable $e) {
            error_log('[assign_groups] ' . $e->getMessage());
            $feedback = 'Si è verificato un errore. Riprova.';
            $feedbackType = 'error';
        }
    }
}

// -----------------------------------------------------------------
// GET: matrice utenti × gruppi
// -----------------------------------------------------------------
$usersStmt = $pdo->query('SELECT user_id, username, first_name, last_name FROM users ORDER BY last_name, first_name');
$allUsers = $usersStmt->fetchAll();

$groupsStmt = $pdo->query('SELECT group_id, group_name FROM groups ORDER BY group_name');
$allGroups = $groupsStmt->fetchAll();

$assignmentsStmt = $pdo->query('SELECT user_id, group_id FROM users_has_groups');
$assignments = [];
foreach ($assignmentsStmt->fetchAll() as $a) {
    $assignments[(int)$a['user_id']][(int)$a['group_id']] = true;
}

$groupHeaderCells = '';
foreach ($allGroups as $g) {
    $groupHeaderCells .= '<th>' . htmlspecialchars(groupLabel($g['group_name']), ENT_QUOTES) . '</th>';
}

$matrixRows = '';
if (empty($allUsers)) {
    $matrixRows = '<tr><td colspan="' . (1 + count($allGroups)) . '"><em>Nessun utente registrato.</em></td></tr>';
} else {
    foreach ($allUsers as $u) {
        $uid = (int)$u['user_id'];
        $cells = '<td>' . htmlspecialchars($u['last_name'] . ' ' . $u['first_name'], ENT_QUOTES)
            . ' <span class="muted">(' . htmlspecialchars($u['username'], ENT_QUOTES) . ')</span></td>';

        foreach ($allGroups as $g) {
            $gid = (int)$g['group_id'];
            $isAssigned = isset($assignments[$uid][$gid]);

            if ($isAssigned) {
                $cells .= '<td><form method="post" action="/services/admin/assign_groups.php" class="inline-form" data-confirm="Confermi la rimozione di questo gruppo?">'
                    . csrfField()
                    . '<input type="hidden" name="action" value="revoke">'
                    . '<input type="hidden" name="user_id" value="' . $uid . '">'
                    . '<input type="hidden" name="group_id" value="' . $gid . '">'
                    . '<button type="submit" class="danger">Rimuovi</button>'
                    . '</form></td>';
            } else {
                $cells .= '<td><form method="post" action="/services/admin/assign_groups.php" class="inline-form">'
                    . csrfField()
                    . '<input type="hidden" name="action" value="assign">'
                    . '<input type="hidden" name="user_id" value="' . $uid . '">'
                    . '<input type="hidden" name="group_id" value="' . $gid . '">'
                    . '<button type="submit">Assegna</button>'
                    . '</form></td>';
            }
        }

        $matrixRows .= '<tr>' . $cells . '</tr>';
    }
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/backend/admin/assign_groups');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('group_header_cells', $groupHeaderCells);
$tpl->setContent('matrix_rows', $matrixRows);

renderHeader('Assegnazione gruppi', 'backend');
echo $tpl->get();
renderFooter();
