<?php
declare(strict_types=1);

/**
 * "Where to go after login": only a path inside this site is accepted
 * (must start with APP_BASE_URL + "/", plain URL characters only).
 */
function safe_next(?string $next): ?string
{
    $next = (string)$next;
    if ($next === '' || strlen($next) > 300) return null;
    $prefix = preg_quote(APP_BASE_URL, '#');
    return preg_match('#^' . $prefix . '/[A-Za-z0-9_\-./?=&%\#]*$#', $next) ? $next : null;
}
