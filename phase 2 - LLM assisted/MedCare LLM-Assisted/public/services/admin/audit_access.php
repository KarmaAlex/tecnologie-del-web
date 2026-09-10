<?php
/**
 * public/services/admin/audit_access.php
 *
 * Service registrato come 'admin_audit_access'.
 * Sola lettura: mostra le righe più recenti di access_audit_log,
 * popolato automaticamente da include/auth.php::logAudit() ad ogni
 * accesso concesso/negato e da login.php ad ogni tentativo di login.
 * Filtro opzionale per azione (LOGIN_OK, LOGIN_FAIL, ACCESS_DENIED...).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';
require_once __DIR__ . '/../../../include/badges.php';

requireService('admin_audit_access');

$pdo = getPDO();

$actionFilter = trim((string)($_GET['action_filter'] ?? ''));
// Whitelist esplicita: mai interpolare un filtro arbitrario in una
// LIKE senza controllo, anche se qui verrebbe comunque bindato come
// parametro — la whitelist evita filtri "silenziosamente vuoti" per
// valori mai esistiti nel log.
$knownActions = ['', 'LOGIN_OK', 'LOGIN_FAIL', 'LOGOUT', 'ACCESS_GRANTED', 'ACCESS_DENIED'];
if (!in_array($actionFilter, $knownActions, true)) {
    $actionFilter = '';
}

$sql = "SELECT a.occurred_at, a.action, a.ip_address,
               u.username, s.service_name
        FROM access_audit_log a
        LEFT JOIN users u ON u.user_id = a.user_id
        LEFT JOIN services s ON s.service_id = a.service_id
        WHERE 1=1";
$params = [];

if ($actionFilter !== '') {
    $sql .= ' AND a.action LIKE :action_filter';
    $params[':action_filter'] = $actionFilter . '%'; // include 'ACCESS_GRANTED:create' etc.
}

$sql .= ' ORDER BY a.occurred_at DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$actionOptions = '<option value="">Tutte le azioni</option>';
foreach (array_slice($knownActions, 1) as $a) {
    $selected = ($actionFilter === $a) ? ' selected' : '';
    $actionOptions .= '<option value="' . htmlspecialchars($a, ENT_QUOTES) . '"' . $selected . '>'
        . htmlspecialchars($a, ENT_QUOTES) . '</option>';
}

$auditRows = '';
if (empty($rows)) {
    $auditRows = '<tr><td colspan="5"><em>Nessuna voce trovata per il filtro selezionato.</em></td></tr>';
} else {
    foreach ($rows as $row) {
        $username = $row['username'] ? htmlspecialchars($row['username'], ENT_QUOTES) : '<span class="muted">—</span>';
        $service = $row['service_name'] ? htmlspecialchars($row['service_name'], ENT_QUOTES) : '<span class="muted">—</span>';
        $ip = $row['ip_address'] ? htmlspecialchars($row['ip_address'], ENT_QUOTES) : '<span class="muted">—</span>';

        $auditRows .= '<tr>'
            . '<td>' . htmlspecialchars(date('d/m/Y H:i:s', strtotime($row['occurred_at'])), ENT_QUOTES) . '</td>'
            . '<td>' . $username . '</td>'
            . '<td>' . $service . '</td>'
            . '<td>' . renderAuditActionBadge($row['action']) . '</td>'
            . '<td>' . $ip . '</td>'
            . '</tr>';
    }
}

$tpl = new Template(dirname(__DIR__, 3) . '/skins/backend/admin/audit_access');
$tpl->setContent('action_options', $actionOptions);
$tpl->setContent('audit_rows', $auditRows);

renderHeader('Registro accessi', 'backend');
echo $tpl->get();
renderFooter();
