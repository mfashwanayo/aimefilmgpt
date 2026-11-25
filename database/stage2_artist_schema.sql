USE aimefilms;

CREATE TABLE IF NOT EXISTS artist_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  stage_name VARCHAR(120) NOT NULL,
  bio TEXT NULL,
  country VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  website_url VARCHAR(255) NULL,
  social_links JSON NULL,
  avatar_path VARCHAR(255) NULL,
  banner_path VARCHAR(255) NULL,
  verification_status ENUM('not_submitted','pending','approved','rejected') NOT NULL DEFAULT 'not_submitted',
  verification_note TEXT NULL,
  submitted_at DATETIME NULL,
  reviewed_at DATETIME NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_artist_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_artist_profile_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artist_applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  stage_name VARCHAR(120) NOT NULL,
  bio TEXT NOT NULL,
  portfolio_url VARCHAR(255) NULL,
  proof_path VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note TEXT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  CONSTRAINT fk_artist_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_artist_app_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_artist_app_status (status, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artist_content (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artist_user_id BIGINT UNSIGNED NOT NULL,
  platform ENUM('aimefilms','filmsnyarwanda','musicspart') NOT NULL,
  content_type ENUM('movie','series','episode','music_video','album','single') NOT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  description TEXT NULL,
  release_date DATE NULL,
  language VARCHAR(80) NULL,
  genre VARCHAR(100) NULL,
  duration_seconds INT UNSIGNED NULL,
  media_path VARCHAR(255) NULL,
  poster_path VARCHAR(255) NULL,
  thumbnail_path VARCHAR(255) NULL,
  cover_path VARCHAR(255) NULL,
  parent_id BIGINT UNSIGNED NULL,
  visibility ENUM('draft','pending','published','rejected','archived') NOT NULL DEFAULT 'pending',
  moderation_note TEXT NULL,
  views_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  likes_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_content_artist FOREIGN KEY (artist_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_content_parent FOREIGN KEY (parent_id) REFERENCES artist_content(id) ON DELETE SET NULL,
  UNIQUE KEY uq_content_slug_platform (platform, slug),
  INDEX idx_content_artist (artist_user_id, created_at),
  INDEX idx_content_visibility (visibility, platform, content_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artist_follows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artist_user_id BIGINT UNSIGNED NOT NULL,
  follower_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_artist_follower (artist_user_id, follower_user_id),
  CONSTRAINT fk_follow_artist FOREIGN KEY (artist_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_follow_follower FOREIGN KEY (follower_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  content_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  comment_text TEXT NOT NULL,
  status ENUM('visible','hidden','reported') NOT NULL DEFAULT 'visible',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comment_content FOREIGN KEY (content_id) REFERENCES artist_content(id) ON DELETE CASCADE,
  CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_comment_content (content_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artist_earnings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artist_user_id BIGINT UNSIGNED NOT NULL,
  content_id BIGINT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'RWF',
  source VARCHAR(80) NOT NULL DEFAULT 'platform',
  status ENUM('pending','approved','paid','reversed') NOT NULL DEFAULT 'pending',
  period_start DATE NULL,
  period_end DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_earning_artist FOREIGN KEY (artist_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_earning_content FOREIGN KEY (content_id) REFERENCES artist_content(id) ON DELETE SET NULL,
  INDEX idx_earning_artist (artist_user_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
