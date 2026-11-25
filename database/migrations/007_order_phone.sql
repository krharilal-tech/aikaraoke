-- AI Karaoke Maker — the phone number used for each order
-- Run manually against an already-installed database with:
--   mysql -u root -p aikaraoke < database/migrations/007_order_phone.sql
--
-- users.phone only holds the latest number, so the admin Orders page showed
-- that one against every past order. Each order now keeps its own. Orders
-- from before this keep NULL (their number wasn't recorded).

SET NAMES utf8mb4;

ALTER TABLE `payment_orders`
    ADD COLUMN `customer_phone` VARCHAR(20) NULL AFTER `package_id`;
