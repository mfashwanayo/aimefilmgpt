AIMEFILMS SHARED AUTHENTICATION BACKEND
=======================================

PROJECT ROOT (your Windows computer):
C:\xampp2\htdocs\aimefilms\

INSTALL
1. Extract the ZIP.
2. Copy/merge its folders into C:\xampp2\htdocs\aimefilms\
3. Open XAMPP Control Panel and start Apache and MySQL.
4. Open http://localhost/phpmyadmin/
5. Choose Import and import database/schema.sql.
   It creates the aimefilms database, roles, users and auth_logs.
6. Confirm config/database.php matches your local MySQL:
   host 127.0.0.1, port 3306, database aimefilms, user root, password blank
   (common default XAMPP settings; change if yours differs).
7. Open:
   http://localhost/aimefilms/public/auth/register.php
8. Register a test account, then log out and log in again.

IMPORTANT FILE LOCATIONS
config/database.php
config/app.php
database/schema.sql
app/bootstrap.php
app/Helpers/auth.php
app/Helpers/csrf.php
app/Helpers/flash.php
app/Services/AuthService.php
app/Services/RoleService.php
app/Middleware/AuthMiddleware.php
public/auth/register.php
public/auth/login.php
public/auth/logout.php
public/auth/forgot-password.php
public/partials/header.php
public/partials/footer.php
admin/users/index.php
admin/users/change-role.php

HOW TO PROTECT A PAGE
At the top of a protected page:
<?php
require_once dirname(__DIR__) . '/app/Middleware/AuthMiddleware.php';
AuthMiddleware::handle(); // any logged-in account
?>

For an admin-only page, adjust the relative path as needed and use:
AuthMiddleware::handle(['admin']);

For an artist or admin page:
AuthMiddleware::handle(['artist', 'admin']);

LOGOUT FORM
Place this in a page after including app/bootstrap.php:
<form method="post" action="/aimefilms/public/auth/logout.php">
  <?= csrf_field() ?>
  <button type="submit">Log out</button>
</form>

ROLE MANAGEMENT
The default role on registration is always "user".
Open /aimefilms/admin/users/ after creating an administrator to manage roles. The page is protected by the admin role. Promote users only through this protected administrator-only workflow.
RoleService::setUserRole($userId, 'artist') or 'admin' updates the role.
Never accept a role value from the public registration form.

SECURITY NOTES
- Passwords are stored with password_hash(PASSWORD_DEFAULT), verified using password_verify.
- All SQL values use PDO prepared statements.
- CSRF token is required for POST registration, login, logout and role updates.
- Session ID is regenerated after successful login.
- Session cookie is HttpOnly, SameSite=Lax and Secure when HTTPS is active.
- Idle and absolute session timeouts are enforced.
- A basic login-attempt lock is session-based. For production, replace/augment it
  with database/IP-based rate limiting, reverse-proxy-aware IP handling and monitoring.
- Configure HTTPS before production deployment.
- Do not expose config files or database backups publicly; keep the project root
  outside the public web root in a production deployment.
- Email verification and password reset token/email delivery are not implemented yet.
- "Remember me" is visibly marked as not enabled; no persistent login token is issued.

URL / BASE PATH
config/app.php currently uses /aimefilms/public, based on the existing local URL.
If you configure a virtual host or change the public URL, update APP_BASE_URL.
