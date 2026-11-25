-- AIMEFILMS Stage 7: free viewing, login required to download
-- Adds TWO new tables; no existing table is changed. Safe to run more than once.
USE aimefilms;

CREATE TABLE IF NOT EXISTS content_downloads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  content_id BIGINT UNSIGNED NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_dl_user_time (user_id, created_at),
  INDEX idx_dl_content (content_id),
  CONSTRAINT fk_dl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_dl_content FOREIGN KEY (content_id) REFERENCES artist_content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A row here means the artist switched downloads OFF for that title (default: downloads on).
CREATE TABLE IF NOT EXISTS content_download_blocks (
  content_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dlblock_content FOREIGN KEY (content_id) REFERENCES artist_content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
