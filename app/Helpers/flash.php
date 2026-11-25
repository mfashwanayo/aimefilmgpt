<?php
declare(strict_types=1);

function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

/** Short alias used by the artist/admin modules: flash('success', 'Saved.') */
function flash(string $type, string $message): void
{
    set_flash($type, $message);
}

function get_flash(): ?array
{
    if (empty($_SESSION['_flash'])) {
        return null;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $flash;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
