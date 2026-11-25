<?php
declare(strict_types=1);

/**
 * Read-only queries behind the public pages. Visitors only ever see content that
 * is 'published' AND whose artist account is 'active'.
 */
final class CatalogService
{
    public const PER_PAGE = 12;
    /** Episodes and album tracks have a parent_id and are reached through their series/album. */
    public const TABS = [
        'aimefilms'      => ['movie', 'series', 'music_video'],
        'filmsnyarwanda' => ['movie', 'series', 'music_video'],
        'musicspart'     => ['single', 'album', 'music_video'],
    ];
    private const TYPES = ['movie', 'series', 'episode', 'music_video', 'album', 'single'];
    public const FROM = "FROM artist_content c
        JOIN users u ON u.id = c.artist_user_id AND u.status = 'active'
        LEFT JOIN artist_profiles p ON p.user_id = c.artist_user_id";
    public const CARD = "c.id, c.platform, c.content_type, c.title, c.genre, c.language, c.release_date, c.duration_seconds,
        c.views_count, c.poster_path, c.cover_path, c.thumbnail_path, c.artist_user_id, COALESCE(p.stage_name, u.name) AS artist_name";

    public function __construct(private PDO $db) {}

    /** @param array{type?:string,q?:string} $f @return array{items:array,total:int,page:int,pages:int} */
    public function listing(string $platform, array $f, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $where = ["c.visibility = 'published'", 'c.platform = :platform', 'c.parent_id IS NULL'];
        $args = ['platform' => $platform];
        $type = (string)($f['type'] ?? '');
        if (in_array($type, self::TYPES, true)) { $where[] = 'c.content_type = :type'; $args['type'] = $type; }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(c.title LIKE :q1 OR c.genre LIKE :q2 OR p.stage_name LIKE :q3 OR u.name LIKE :q4)';
            $args += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $from = self::FROM . ' WHERE ' . implode(' AND ', $where);

        $st = $this->db->prepare('SELECT COUNT(*) ' . $from);
        $st->execute($args);
        $total = (int)$st->fetchColumn();
        $perPage = max(1, min($perPage, 60));
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $st = $this->db->prepare('SELECT ' . self::CARD . ' ' . $from . ' ORDER BY c.created_at DESC, c.id DESC LIMIT :lim OFFSET :off');
        foreach ($args as $k => $v) $st->bindValue(':' . $k, $v);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function trending(string $platform, int $limit = 6): array
    {
        $st = $this->db->prepare('SELECT ' . self::CARD . ' ' . self::FROM . "
            WHERE c.visibility = 'published' AND c.platform = :p AND c.parent_id IS NULL AND c.views_count > 0
            ORDER BY c.views_count DESC, c.id DESC LIMIT :lim");
        $st->bindValue(':p', $platform);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Full row for the watch/listen page. Preview mode skips the published check (owner/admin only). */
    public function find(int $id, bool $preview = false): ?array
    {
        $sql = 'SELECT c.*, COALESCE(p.stage_name, u.name) AS artist_name, p.avatar_path AS artist_avatar '
             . 'FROM artist_content c JOIN users u ON u.id = c.artist_user_id LEFT JOIN artist_profiles p ON p.user_id = c.artist_user_id WHERE c.id = ?';
        if (!$preview) $sql .= " AND c.visibility = 'published' AND u.status = 'active'";
        $st = $this->db->prepare($sql);
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function children(int $parentId): array
    {
        $st = $this->db->prepare('SELECT ' . self::CARD . ' ' . self::FROM . "
            WHERE c.parent_id = ? AND c.visibility = 'published' ORDER BY COALESCE(c.release_date, '9999-12-31') ASC, c.id ASC");
        $st->execute([$parentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function parent(int $parentId): ?array
    {
        return $this->find($parentId);
    }

    /** @return array{prev:?array,next:?array} siblings of an episode/track */
    public function neighbors(array $item): array
    {
        $out = ['prev' => null, 'next' => null];
        if (empty($item['parent_id'])) return $out;
        $list = $this->children((int)$item['parent_id']);
        foreach ($list as $i => $row) {
            if ((int)$row['id'] === (int)$item['id']) {
                $out['prev'] = $list[$i - 1] ?? null;
                $out['next'] = $list[$i + 1] ?? null;
            }
        }
        return $out;
    }

    public function moreFromArtist(int $artistId, int $excludeId, int $limit = 6): array
    {
        $st = $this->db->prepare('SELECT ' . self::CARD . ' ' . self::FROM . "
            WHERE c.artist_user_id = :a AND c.id <> :x AND c.visibility = 'published' AND c.parent_id IS NULL
            ORDER BY c.created_at DESC, c.id DESC LIMIT :lim");
        $st->bindValue(':a', $artistId, PDO::PARAM_INT);
        $st->bindValue(':x', $excludeId, PDO::PARAM_INT);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Public artist profile (approved artists with active accounts only). */
    public function artist(int $userId): ?array
    {
        $st = $this->db->prepare("SELECT p.*, u.name AS user_name FROM artist_profiles p JOIN users u ON u.id = p.user_id
            WHERE p.user_id = ? AND p.verification_status = 'approved' AND u.status = 'active'");
        $st->execute([$userId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;
        $f = $this->db->prepare('SELECT COUNT(*) FROM artist_follows WHERE artist_user_id = ?');
        $f->execute([$userId]);
        $a['followers'] = (int)$f->fetchColumn();
        return $a;
    }

    public function artistContent(int $userId, int $limit = 48): array
    {
        $st = $this->db->prepare('SELECT ' . self::CARD . ' ' . self::FROM . "
            WHERE c.artist_user_id = :a AND c.visibility = 'published' AND c.parent_id IS NULL
            ORDER BY c.created_at DESC, c.id DESC LIMIT :lim");
        $st->bindValue(':a', $userId, PDO::PARAM_INT);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recordView(int $id): void
    {
        $this->db->prepare("UPDATE artist_content SET views_count = views_count + 1 WHERE id = ? AND visibility = 'published'")->execute([$id]);
    }
}
