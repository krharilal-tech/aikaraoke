-- AI Karaoke Maker — job-completion email notifications + auto-cleanup of old files
-- Run manually against an already-installed database with:
--   mysql -u root -p aikaraoke < database/migrations/004_notifications_and_cleanup.sql

SET NAMES utf8mb4;

ALTER TABLE `jobs`
    ADD COLUMN `notified_at` DATETIME NULL AFTER `error_message`,
    ADD COLUMN `expired_at` DATETIME NULL AFTER `notified_at`;

-- One-time backfill: every job that was already finished before this
-- feature existed gets marked as already-notified, so it doesn't suddenly
-- email its owner the moment SMTP gets configured. Only jobs that finish
-- from this point forward are real notification candidates.
UPDATE `jobs`
SET `notified_at` = UTC_TIMESTAMP()
WHERE `state` IN ('completed', 'failed')
  AND `notified_at` IS NULL;
