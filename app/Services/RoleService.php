<?php
declare(strict_types=1);

/**
 * Role changes must only be called from administrator-only pages/actions.
 */
final class RoleService
{
    public function __construct(private PDO $pdo) {}

    public function setUserRole(int $userId, string $roleName): bool
    {
        if (!in_array($roleName, ['user', 'artist', 'admin'], true)) {
            throw new InvalidArgumentException('Unsupported role.');
        }
        $role = $this->pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $role->execute(['name' => $roleName]);
        $roleRow = $role->fetch();
        if (!$roleRow) {
            throw new RuntimeException('Role does not exist.');
        }

        $update = $this->pdo->prepare('UPDATE users SET role_id = :role_id, updated_at = NOW() WHERE id = :id');
        $update->execute(['role_id' => $roleRow['id'], 'id' => $userId]);
        return $update->rowCount() > 0;
    }

    public function listUsers(int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.status, u.created_at, r.name AS role
             FROM users u JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
