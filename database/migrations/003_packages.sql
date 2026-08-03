-- AI Karaoke Maker — purchasable credit packages (admin-manageable)
-- Run manually against an already-installed database with:
--   mysql -u root -p aikaraoke < database/migrations/003_packages.sql

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- packages — the pricing tiers shown on /pricing. Seeded with the 3 tiers
-- initially hardcoded into the pricing page; admins can add/edit/retire
-- packages from /admin/packages instead of that being a code change.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `packages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `credits` INT UNSIGNED NOT NULL,
    `price_inr` DECIMAL(10,2) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `packages` (`name`, `credits`, `price_inr`, `is_active`, `sort_order`) VALUES
    ('Starter', 10, 299.00, 1, 1),
    ('Popular', 30, 699.00, 1, 2),
    ('Bulk', 100, 1999.00, 1, 3);
