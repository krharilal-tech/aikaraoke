-- AI Karaoke Maker — initial schema
-- Run via the install wizard (public/install) or manually with:
--   mysql -u root -p aikaraoke < database/migrations/001_create_tables.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- jobs — one row per karaoke generation request, drives the whole pipeline
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `youtube_url` VARCHAR(500) NOT NULL,
    `youtube_video_id` VARCHAR(32) NULL,
    `title` VARCHAR(255) NULL,
    `channel` VARCHAR(255) NULL,
    `thumbnail_url` VARCHAR(500) NULL,
    `duration_sec` INT UNSIGNED NULL,
    `keep_vocals` TINYINT(1) NOT NULL DEFAULT 0,
    `state` ENUM(
        'queued',
        'downloading',
        'extracting_audio',
        'separating_vocals',
        'extracting_lyrics',
        'synchronizing',
        'generating_images',
        'waiting_for_user',
        'rendering_video',
        'completed',
        'failed'
    ) NOT NULL DEFAULT 'queued',
    `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `eta_seconds` INT UNSIGNED NULL,
    `error_message` TEXT NULL,
    `storage_path` VARCHAR(500) NULL,
    `pid` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `jobs_state_index` (`state`),
    KEY `jobs_user_id_index` (`user_id`),
    CONSTRAINT `jobs_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- lyrics — one row per job (word-level timestamps stored as JSON)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lyrics` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id` BIGINT UNSIGNED NOT NULL,
    `source` ENUM('lrclib', 'musixmatch', 'genius', 'whisperx') NOT NULL,
    `raw_text` MEDIUMTEXT NULL,
    `words_json` JSON NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `lyrics_job_id_index` (`job_id`),
    CONSTRAINT `lyrics_job_id_fk` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- backgrounds — the 3 AI-generated candidate images per job
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backgrounds` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id` BIGINT UNSIGNED NOT NULL,
    `prompt` TEXT NOT NULL,
    `image_path` VARCHAR(500) NOT NULL,
    `is_selected` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `backgrounds_job_id_index` (`job_id`),
    CONSTRAINT `backgrounds_job_id_fk` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- videos — the final rendered MP4 per job
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `videos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id` BIGINT UNSIGNED NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `duration_sec` INT UNSIGNED NULL,
    `resolution` VARCHAR(20) NOT NULL DEFAULT '1920x1080',
    `file_size_bytes` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `videos_job_id_index` (`job_id`),
    CONSTRAINT `videos_job_id_fk` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- settings — key/value store backing the Settings page
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- logs — structured execution log (PHP, Python, FFmpeg, OpenAI)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id` BIGINT UNSIGNED NULL,
    `level` ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'info',
    `source` ENUM('php', 'python', 'ffmpeg', 'openai') NOT NULL DEFAULT 'php',
    `message` TEXT NOT NULL,
    `context` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `logs_job_id_index` (`job_id`),
    KEY `logs_created_at_index` (`created_at`),
    CONSTRAINT `logs_job_id_fk` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- default settings
-- ---------------------------------------------------------------------------
INSERT INTO `settings` (`key`, `value`) VALUES
    ('image_model', 'gpt-image-1'),
    ('prompt_model', 'gpt-4o-mini'),
    ('background_source', 'openai'),
    ('local_backgrounds_path', 'storage/backgrounds'),
    ('ffmpeg_path', 'ffmpeg'),
    ('ffprobe_path', 'ffprobe'),
    ('python_path', 'python'),
    ('yt_dlp_path', 'yt-dlp'),
    ('demucs_model', 'htdemucs'),
    ('whisperx_model', 'base'),
    ('transcription_language', 'auto'),
    ('max_video_length_seconds', '600'),
    ('temp_storage_path', 'storage/jobs')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

SET FOREIGN_KEY_CHECKS = 1;
