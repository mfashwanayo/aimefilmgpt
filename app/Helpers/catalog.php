<?php
declare(strict_types=1);

require_once __DIR__ . '/admin.php';   // platform_label(), type_label(), upload_url(), admin_pagination()

function fmt_duration(?int $seconds): string
{
    if (!$seconds) return '';
    return $seconds >= 3600 ? gmdate('G:i:s', $seconds) : gmdate('i:s', $seconds);
}

function card_image(array $i): ?string
{
    return upload_url($i['poster_path'] ?? null) ?: upload_url($i['cover_path'] ?? null) ?: upload_url($i['thumbnail_path'] ?? null);
}

function type_icon(string $type): string
{
    return ['movie' => '▶', 'series' => '▣', 'episode' => '▶', 'music_video' => '◉', 'album' => '◉', 'single' => '♫'][$type] ?? '▶';
}

function is_audio_path(?string $path): bool
{
    return $path !== null && (bool)preg_match('/\.(mp3|m4a|wav)$/i', $path);
}

function watch_url(int $id): string
{
    return APP_BASE_URL . '/watch.php?id=' . $id;
}

function artist_url(int $id): string
{
    return APP_BASE_URL . '/artists.php?id=' . $id;
}

/** One catalogue card (poster, title, artist, type). All values escaped. */
function render_card(array $i): string
{
    $img = card_image($i);
    $square = $i['platform'] === 'musicspart' || in_array($i['content_type'], ['single', 'album'], true);
    $style = $img ? ' style="background-image:url(\'' . e($img) . '\')"' : '';
    $meta = e(type_label($i['content_type']));
    if (!empty($i['genre'])) $meta .= ' · ' . e($i['genre']);
    return '<article class="catalog-card"><a class="catalog-thumb' . ($square ? ' square' : '') . ($img ? '' : ' no-image') . '" href="' . e(watch_url((int)$i['id'])) . '"' . $style . ' aria-label="' . e($i['title']) . '">'
        . ($img ? '' : '<span>' . type_icon($i['content_type']) . '</span>') . '<i class="play-badge">' . type_icon($i['content_type']) . '</i></a>'
        . '<div class="catalog-meta"><a class="catalog-title" href="' . e(watch_url((int)$i['id'])) . '">' . e($i['title']) . '</a>'
        . '<a class="catalog-artist" href="' . e(artist_url((int)$i['artist_user_id'])) . '">' . e($i['artist_name']) . '</a>'
        . '<small>' . $meta . '</small></div></article>';
}
