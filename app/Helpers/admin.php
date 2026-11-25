<?php
declare(strict_types=1);

/** Small view helpers shared by the admin pages. */

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function platform_label(string $platform): string
{
    return ['aimefilms' => 'AIMEFILMS', 'filmsnyarwanda' => 'FilmsNyarwanda', 'musicspart' => 'MusicSpart'][$platform] ?? $platform;
}

function type_label(string $type): string
{
    return ucfirst(str_replace('_', ' ', $type));
}

/** Previous / next links that keep the current filters. */
function admin_pagination(array $query, int $page, int $pages): string
{
    if ($pages <= 1) return '';
    $link = fn(int $p, string $text, bool $on) => $on
        ? '<a class="page-link" href="?' . e(http_build_query(array_merge($query, ['page' => $p]))) . '">' . $text . '</a>'
        : '<span class="page-link disabled">' . $text . '</span>';
    return '<nav class="pagination" aria-label="Pages">' . $link($page - 1, '← Previous', $page > 1)
        . '<span class="page-info">Page ' . $page . ' of ' . $pages . '</span>' . $link($page + 1, 'Next →', $page < $pages) . '</nav>';
}

/** Public URL for an uploaded file, or null if the stored path is not an expected upload path. */
function upload_url(?string $path): ?string
{
    if ($path === null || !preg_match('#^uploads/artists/\d+/[a-f0-9]{32}\.[a-z0-9]{2,4}$#', $path)) return null;
    return APP_BASE_URL . '/' . $path;
}
