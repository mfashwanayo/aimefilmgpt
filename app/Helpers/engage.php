<?php
declare(strict_types=1);

/**
 * A one-button POST form for interact.php. $label must already be safe HTML
 * (only pass literals and cast numbers).
 */
function engage_form(string $action, array $fields, string $label, bool $on = false, string $extraClass = ''): string
{
    $h = '<form method="post" action="' . e(APP_BASE_URL) . '/interact.php" class="inline-form">' . csrf_field()
       . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . e((string)$k) . '" value="' . e((string)$v) . '">';
    return $h . '<button type="submit" class="btn btn-small ' . ($on ? 'btn-primary is-on' : 'btn-glass') . ($extraClass ? ' ' . e($extraClass) : '')
        . '" aria-pressed="' . ($on ? 'true' : 'false') . '">' . $label . '</button></form>';
}

/** Same look as engage_form but for visitors who are not logged in. */
function login_button(string $label): string
{
    return '<a class="btn btn-small btn-glass" href="' . e(APP_BASE_URL) . '/auth/login.php">' . $label . '</a>';
}
