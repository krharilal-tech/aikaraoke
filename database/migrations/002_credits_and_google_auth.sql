-- AI Karaoke Maker — credits (pay-per-generation) + Google OAuth login
-- Run manually against an already-installed database with:
--   mysql -u root -p aikaraoke < database/migrations/002_credits_and_google_auth.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users — password_hash becomes optional (Google-only accounts have none),
-- name is populated from registration or the Google profile, google_id links
-- a Google-authenticated login to its account.
-- ---------------------------------------------------------------------------
ALTER TABLE `users`
    MODIFY COLUMN `password_hash` VARCHAR(255) NULL,
    ADD COLUMN `name` VARCHAR(190) NULL AFTER `email`,
    ADD COLUMN `google_id` VARCHAR(255) NULL AFTER `password_hash`,
    ADD UNIQUE KEY `users_google_id_unique` (`google_id`);

-- ---------------------------------------------------------------------------
-- credits — a ledger, not a balance column: every grant/spend/refund is its
-- own row (delta can be negative), so a user's balance is SUM(delta) and
-- there's a permanent audit trail of where credits came from and went
-- (signup bonus, a paid pack, a job consuming one, a refund for a job that
-- failed on us). job_id links a consumption/refund pair back to the job that
-- caused them; payment_id will link a purchase once Razorpay is wired in.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `credits` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `delta` INT NOT NULL,
    `reason` VARCHAR(50) NOT NULL,
    `job_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `credits_user_id_index` (`user_id`),
    KEY `credits_job_id_index` (`job_id`),
    CONSTRAINT `credits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `credits_job_id_foreign` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
