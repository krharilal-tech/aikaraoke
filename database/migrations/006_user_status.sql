-- AI Karaoke Maker — user blocking (admin can bar an account without deleting it)
-- Run manually against an already-installed database with:
--   mysql -u root -p aikaraoke < database/migrations/006_user_status.sql

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `status` ENUM('active', 'blocked') NOT NULL DEFAULT 'active' AFTER `role`,
    ADD COLUMN `blocked_at` DATETIME NULL AFTER `status`,
    ADD COLUMN `blocked_reason` VARCHAR(190) NULL AFTER `blocked_at`;
