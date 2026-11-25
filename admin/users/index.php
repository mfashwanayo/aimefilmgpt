<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/RoleService.php';
require_role('admin');

$service = new RoleService(db());
$users = $service->listUsers(200, 0);
$flash = get_flash();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>User management — AIMEFILMS</title><link rel="stylesheet" href="/aimefilms/public/assets/css/main.css"><link rel="stylesheet" href="/aimefilms/public/assets/css/auth-backend.css"></head><body>
<main class="section"><span class="eyebrow">ADMINISTRATION</span><h1>User management</h1><p>Manage account roles. Only administrators can access this page.</p>
<?php if ($flash): ?><div class="server-notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px"><thead><tr><th style="padding:12px">Name</th><th>Email</th><th>Role</th><th>Status</th><th>Change role</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr style="border-top:1px solid #ffffff20"><td style="padding:12px"><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e($user['role']) ?></td><td><?= e($user['status']) ?></td><td>
<?php if ((int)$user['id'] !== (int)current_user()['id']): ?><form method="post" action="change-role.php" style="display:flex;gap:8px;align-items:center"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><select name="role" style="padding:8px;background:#17161b;color:white;border:1px solid #444;border-radius:8px"><option value="user" <?= $user['role']==='user'?'selected':'' ?>>User</option><option value="artist" <?= $user['role']==='artist'?'selected':'' ?>>Artist</option><option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option></select><button class="btn btn-small btn-primary" type="submit">Save</button></form><?php else: ?>Your account<?php endif; ?>
</td></tr><?php endforeach; ?>
</tbody></table></div><p><a class="btn btn-glass" href="/aimefilms/public/">Back to website</a></p></main></body></html>
