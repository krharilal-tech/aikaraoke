-- AI Karaoke Maker — Cashfree payment orders (purchasing credit packages)
-- Run manually against an already-installed database with:
--   mysql -u root -p aikaraoke < database/migrations/005_payments.sql

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- payment_orders — one row per checkout attempt, created before the user is
-- ever sent to Cashfree so the webhook (or the return-page fallback, see
-- PaymentController) always has something to look up by cf_order_id. amount
-- and credits are snapshotted from the package at purchase time so a later
-- admin edit to a package's price/credits doesn't retroactively change what
-- an already-placed order is worth.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `package_id` BIGINT UNSIGNED NOT NULL,
    `cf_order_id` VARCHAR(64) NOT NULL,
    `cf_payment_id` VARCHAR(64) NULL,
    `amount_inr` DECIMAL(10,2) NOT NULL,
    `credits` INT UNSIGNED NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'created',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payment_orders_cf_order_id_unique` (`cf_order_id`),
    KEY `payment_orders_user_id_index` (`user_id`),
    CONSTRAINT `payment_orders_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `payment_orders_package_id_fk` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- credits — link a `purchase` ledger row back to the order that funded it,
-- same nullable-FK-for-traceability pattern as the existing job_id column.
-- ---------------------------------------------------------------------------
ALTER TABLE `credits`
    ADD COLUMN `payment_order_id` BIGINT UNSIGNED NULL AFTER `job_id`,
    ADD KEY `credits_payment_order_id_index` (`payment_order_id`),
    ADD CONSTRAINT `credits_payment_order_id_fk` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_orders` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- users — Cashfree's Orders API requires a customer phone number, which
-- nothing in this app collects today. Captured once at first checkout
-- (PaymentController::checkout()) and reused on later purchases.
-- ---------------------------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN `phone` VARCHAR(20) NULL AFTER `name`;
