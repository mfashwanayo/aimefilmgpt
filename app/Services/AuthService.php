<?php
declare(strict_types=1);

final class AuthService
{
    public function __construct(private PDO $pdo) {}

    public function register(string $name, string $email, string $password): int
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Enter a name between 2 and 100 characters.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (strlen($password) < 8 || strlen($password) > 200) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        $check = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);
        if ($check->fetch()) {
            throw new InvalidArgumentException('An account with this email already exists. Please log in.');
        }

        $roleStmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = 'user' LIMIT 1");
        $roleStmt->execute();
        $role = $roleStmt->fetch();
        if (!$role) {
            throw new RuntimeException('Default user role is missing. Import database/schema.sql again.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $this->pdo->prepare(
            'INSERT INTO users (role_id, name, email, password_hash, status, created_at, updated_at)
             VALUES (:role_id, :name, :email, :password_hash, "active", NOW(), NOW())'
        );
        $insert->execute([
            'role_id' => $role['id'],
            'name' => $name,
            'email' => $email,
            'password_hash' => $hash,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findActiveUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.password_hash, r.name AS role, u.status
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email AND u.status = "active" LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function publicUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, r.name AS role
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.status = "active" LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * @throws InvalidArgumentException with a user-facing message
     */
    public function changePassword(int $userId, string $current, string $new): void
    {
        if (strlen($new) < 8 || strlen($new) > 200) {
            throw new InvalidArgumentException('The new password must be 8 to 200 characters.');
        }
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = :id AND status = "active" LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($current, (string)$hash)) {
            throw new InvalidArgumentException('Your current password is not correct.');
        }
        if (hash_equals($current, $new)) {
            throw new InvalidArgumentException('Choose a new password that is different from the current one.');
        }
        $upd = $this->pdo->prepare('UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id');
        $upd->execute(['h' => password_hash($new, PASSWORD_DEFAULT), 'id' => $userId]);
    }
}
