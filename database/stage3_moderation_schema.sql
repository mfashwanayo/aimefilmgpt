-- AIMEFILMS Stage 3: Admin moderation
-- Adds ONE new table. No existing table is altered or dropped.
USE aimefilms;

CREATE TABLE IF NOT EXISTS moderation_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('content','comment') NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(30) NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_modlog_entity (entity_type, entity_id, created_at),
  INDEX idx_modlog_admin (admin_user_id, created_at),
  CONSTRAINT fk_modlog_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
