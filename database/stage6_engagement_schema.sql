-- AIMEFILMS Stage 6: likes, watchlist/favourites, comment reports
-- Requires Stages 2-5. Adds THREE new tables; no existing table is changed.
-- (Follows use the existing artist_follows table; comments use content_comments.)
USE aimefilms;

CREATE TABLE IF NOT EXISTS content_likes (
  user_id BIGINT UNSIGNED NOT NULL,
  content_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, content_id),
  INDEX idx_likes_content (content_id),
  CONSTRAINT fk_like_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_like_content FOREIGN KEY (content_id) REFERENCES artist_content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_lists (
  user_id BIGINT UNSIGNED NOT NULL,
  content_id BIGINT UNSIGNED NOT NULL,
  list_type ENUM('watchlist','favorite') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, content_id, list_type),
  INDEX idx_lists_user (user_id, list_type, created_at),
  CONSTRAINT fk_list_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_list_content FOREIGN KEY (content_id) REFERENCES artist_content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comment_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  comment_id BIGINT UNSIGNED NOT NULL,
  reporter_user_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_report_once (comment_id, reporter_user_id),
  CONSTRAINT fk_report_comment FOREIGN KEY (comment_id) REFERENCES content_comments(id) ON DELETE CASCADE,
  CONSTRAINT fk_report_user FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
