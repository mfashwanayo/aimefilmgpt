<?php
declare(strict_types=1);

/**
 * Rules for downloading. Watching is free for everyone; downloading needs a
 * login, a published title, an artist who has not switched downloads off,
 * and stays under a daily limit per account.
 */
final class DownloadService
{
    public const DAILY_LIMIT = 20;

    public function __construct(private PDO $db) {}

    /**
     * @return array{id:int,title:string,artist_name:string,media_path:string} the item, if the user may download it now
     * @throws RuntimeException with a user-facing message
     */
    public function authorize(int $userId, int $contentId): array
    {
        $st = $this->db->prepare("SELECT c.id, c.title, c.media_path, COALESCE(p.stage_name, u.name) AS artist_name
            FROM artist_content c JOIN users u ON u.id = c.artist_user_id AND u.status = 'active'
            LEFT JOIN artist_profiles p ON p.user_id = c.artist_user_id
            WHERE c.id = ? AND c.visibility = 'published'");
        $st->execute([$contentId]);
        $item = $st->fetch(PDO::FETCH_ASSOC);
        if (!$item) throw new RuntimeException('This title is not available.');
        if (empty($item['media_path'])) throw new RuntimeException('There is no file to download for this title.');
        if ($this->isBlocked($contentId)) throw new RuntimeException('The artist has turned off downloads for this title. You can still watch or listen for free.');

        $since = date('Y-m-d H:i:s', time() - 86400);
        $n = $this->db->prepare('SELECT COUNT(*) FROM content_downloads WHERE user_id = ? AND created_at >= ?');
        $n->execute([$userId, $since]);
        if ((int)$n->fetchColumn() >= self::DAILY_LIMIT) {
            throw new RuntimeException('You have reached today\'s download limit (' . self::DAILY_LIMIT . '). Please try again tomorrow.');
        }
        return $item;
    }

    public function record(int $userId, int $contentId, ?string $ip): void
    {
        $st = $this->db->prepare('INSERT INTO content_downloads (user_id, content_id, ip_address, created_at) VALUES (?,?,?,?)');
        $st->execute([$userId, $contentId, $ip !== null ? substr($ip, 0, 45) : null, date('Y-m-d H:i:s')]);
    }

    public function isBlocked(int $contentId): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM content_download_blocks WHERE content_id = ?');
        $st->execute([$contentId]);
        return (bool)$st->fetchColumn();
    }

    /** Artist switch. Does not change the review status of the title. */
    public function setAllowed(int $contentId, int $artistId, bool $allowed): void
    {
        $own = $this->db->prepare('SELECT 1 FROM artist_content WHERE id = ? AND artist_user_id = ?');
        $own->execute([$contentId, $artistId]);
        if (!$own->fetchColumn()) throw new RuntimeException('Content not found.');
        if ($allowed) {
            $this->db->prepare('DELETE FROM content_download_blocks WHERE content_id = ?')->execute([$contentId]);
        } elseif (!$this->isBlocked($contentId)) {
            $this->db->prepare('INSERT INTO content_download_blocks (content_id) VALUES (?)')->execute([$contentId]);
        }
    }

    /** @return array<int,int> content id => number of downloads, for one artist's titles */
    public function countsFor(int $artistId): array
    {
        $st = $this->db->prepare('SELECT d.content_id, COUNT(*) n FROM content_downloads d
            JOIN artist_content c ON c.id = d.content_id WHERE c.artist_user_id = ? GROUP BY d.content_id');
        $st->execute([$artistId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int)$r['content_id']] = (int)$r['n'];
        return $out;
    }

    /** @return int[] ids of this artist's titles with downloads switched off */
    public function blockedIds(int $artistId): array
    {
        $st = $this->db->prepare('SELECT b.content_id FROM content_download_blocks b JOIN artist_content c ON c.id = b.content_id WHERE c.artist_user_id = ?');
        $st->execute([$artistId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
