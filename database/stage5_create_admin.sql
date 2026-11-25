-- AIMEFILMS: create / promote the administrator account hybert1@gmail.com
-- Safe to run more than once. The password is stored only as a bcrypt hash.
USE aimefilms;

INSERT IGNORE INTO roles (name, description) VALUES ('admin', 'Platform administrator');

INSERT INTO users (role_id, name, email, password_hash, status, email_verified_at)
SELECT id, 'Hybert', 'hybert1@gmail.com', '$2y$10$jwgvqSGO2xrv67VsJuBBOuuppXu7nohBsM8uIt0Bgb3HEuECu1ERq', 'active', NOW()
FROM roles WHERE name = 'admin'
ON DUPLICATE KEY UPDATE
  role_id = VALUES(role_id),
  password_hash = VALUES(password_hash),
  status = 'active',
  email_verified_at = COALESCE(email_verified_at, NOW());

-- Check: should show one row with role = admin
SELECT u.id, u.name, u.email, r.name AS role, u.status
FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = 'hybert1@gmail.com';
