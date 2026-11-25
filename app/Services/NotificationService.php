<?php
declare(strict_types=1);

/**
 * In-app notifications. Sending is best-effort: a failure is logged and never
 * breaks the action (approval, upload...) that triggered it.
 */
final class NotificationService
{
    public function __construct(private PDO $db) {}

    public function notify(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        try {
            $st = $this->db->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?,?,?,?,?)');
            $st->execute([$userId, $type, mb_substr($title, 0, 150), $this->cut($body, 500), $this->cut($link, 255)]);
        } catch (Throwable $e) {
            error_log('Notification failed: ' . $e->getMessage());
        }
    }

    /** One notification for every active admin. */
    public function notifyAdmins(string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        try {
            $st = $this->db->prepare("INSERT INTO notifications (user_id, type, title, body, link)
                SELECT u.id, ?, ?, ?, ? FROM users u JOIN roles r ON r.id = u.role_id
                WHERE r.name = 'admin' AND u.status = 'active'");
            $st->execute([$type, mb_substr($title, 0, 150), $this->cut($body, 500), $this->cut($link, 255)]);
        } catch (Throwable $e) {
            error_log('Admin notification failed: ' . $e->getMessage());
        }
    }

    public function unreadCount(int $userId): int
    {
        try {
            $st = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $st->execute([$userId]);
            return (int)$st->fetchColumn();
        } catch (Throwable $e) {   // table not imported yet: hide the badge instead of breaking pages
            error_log('Notification count failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return array{items:array,total:int,page:int,pages:int} */
    public function list(int $userId, int $page = 1, int $perPage = 20): array
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
        $st->execute([$userId]);
        $total = (int)$st->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $st = $this->db->prepare('SELECT id, type, title, body, link, is_read, created_at FROM notifications
            WHERE user_id = :u ORDER BY created_at DESC, id DESC LIMIT :lim OFFSET :off');
        $st->bindValue(':u', $userId, PDO::PARAM_INT);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Marks one of the user's own notifications read. Returns its link (or null). */
    public function open(int $id, int $userId): ?string
    {
        $st = $this->db->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
        $st->execute([$id, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        $link = (string)($row['link'] ?? '');
        // Only same-site absolute paths are ever followed.
        return ($link !== '' && $link[0] === '/' && !str_starts_with($link, '//')) ? $link : null;
    }

    public function markAllRead(int $userId): void
    {
        $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$userId]);
    }

    private function cut(?string $v, int $max): ?string
    {
        return $v === null || $v === '' ? null : mb_substr($v, 0, $max);
    }
}
