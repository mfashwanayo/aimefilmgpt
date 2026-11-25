<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_or_abort(): void
{
    $submitted = $_POST['_csrf'] ?? '';
    $known = $_SESSION['_csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($known) ||
        $known === '' || !hash_equals($known, $submitted)) {
        http_response_code(419);
        exit('Your form session expired or the security token was invalid. Go back, refresh the page and try again.');
    }
}

function rotate_csrf_token(): void
{
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
