<?php
/**
 * include/badges.php
 *
 * Centralizza la mappatura stato-di-dominio -> classe badge CSS (definite
 * in public/assets/css/tokens.css). Serve a garantire che lo stesso stato
 * (es. uno slot "booked") produca sempre lo stesso badge, sia che venga
 * renderizzato in un verticale frontend (paziente/medico) sia in uno
 * backend (admin) — i controller non devono mai scrivere le classi CSS
 * a mano.
 *
 * Uso tipico in un controller:
 *   echo renderSlotBadge($slot['status']);
 */

declare(strict_types=1);

/**
 * Badge generico: costruisce il markup <span class="badge badge-X">Testo</span>.
 * Escapa sempre il testo (mai passare HTML grezzo come $label).
 */
function renderBadge(string $variantClass, string $label): string
{
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    return '<span class="badge ' . $variantClass . '">' . $safeLabel . '</span>';
}

/**
 * appointment_slots.status -> badge (open/booked/cancelled).
 */
function renderSlotBadge(string $status): string
{
    return match ($status) {
        'open'      => renderBadge('badge-slot-open', 'Libero'),
        'booked'    => renderBadge('badge-slot-booked', 'Prenotato'),
        'cancelled' => renderBadge('badge-slot-cancelled', 'Annullato'),
        default     => renderBadge('badge-neutral', ucfirst($status)),
    };
}

/**
 * users.is_active -> badge (attivo/disattivato).
 */
function renderUserStatusBadge(bool $isActive): string
{
    return $isActive
        ? renderBadge('badge-user-active', 'Attivo')
        : renderBadge('badge-user-inactive', 'Disattivato');
}

/**
 * access_audit_log.action -> badge. Riconosce i prefissi usati da
 * logAudit() in include/auth.php (es. 'ACCESS_GRANTED:create').
 */
function renderAuditActionBadge(string $action): string
{
    return match (true) {
        str_starts_with($action, 'ACCESS_GRANTED') => renderBadge('badge-audit-granted', 'Consentito'),
        str_starts_with($action, 'ACCESS_DENIED')   => renderBadge('badge-audit-denied', 'Negato'),
        $action === 'LOGIN_OK'                      => renderBadge('badge-audit-login-ok', 'Login OK'),
        $action === 'LOGIN_FAIL'                     => renderBadge('badge-audit-login-fail', 'Login fallito'),
        $action === 'LOGOUT'                         => renderBadge('badge-neutral', 'Logout'),
        default                                      => renderBadge('badge-neutral', $action),
    };
}
