<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PaymentOrder extends Model
{
    protected static string $table = 'payment_orders';

    /** The user clicked "Buy Now" but hasn't gone on to Cashfree yet. */
    public const STATUS_STARTED = 'started';
    /** Sent to Cashfree's checkout; waiting for the payment's outcome. */
    public const STATUS_CREATED = 'created';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    /** A repeat "Buy Now" click within this window reuses the same order. */
    private const STARTED_REUSE_MINUTES = 30;

    /**
     * @return array<string, mixed>|null
     */
    public static function findByCfOrderId(string $cfOrderId): ?array
    {
        return static::firstWhere(['cf_order_id' => $cfOrderId]);
    }

    /**
     * The user's recent "Buy Now" order for this package that hasn't gone on
     * to Cashfree yet, so clicking Buy Now again doesn't add another row.
     *
     * @return array<string, mixed>|null
     */
    public static function findRecentStarted(int $userId, int $packageId): ?array
    {
        return static::db()->fetchOne(
            'SELECT * FROM `payment_orders`
             WHERE `user_id` = ? AND `package_id` = ? AND `status` = ?
               AND `created_at` >= (NOW() - INTERVAL ' . self::STARTED_REUSE_MINUTES . ' MINUTE)
             ORDER BY `id` DESC LIMIT 1',
            [$userId, $packageId, self::STATUS_STARTED]
        );
    }

    /**
     * Marks the order paid unless it already is. Returns whether this call
     * did it, so credits are granted exactly once even when the webhook and
     * the return page race. An order marked failed can still become paid:
     * a Cashfree order allows another try after a failed payment.
     */
    public static function markPaid(int $id, string $cfPaymentId): bool
    {
        return static::db()->execute(
            'UPDATE `payment_orders` SET `status` = ?, `cf_payment_id` = NULLIF(?, \'\') WHERE `id` = ? AND `status` <> ?',
            [self::STATUS_PAID, $cfPaymentId, $id, self::STATUS_PAID]
        ) > 0;
    }

    public static function setPaymentId(int $id, string $cfPaymentId): void
    {
        static::update($id, ['cf_payment_id' => $cfPaymentId]);
    }

    /**
     * Only transitions an order that's still `created` — an order already
     * marked paid must never be downgraded by a late or duplicate failure
     * event (webhooks can arrive out of order or more than once). Returns
     * whether this call changed it, so the failure is reported once.
     */
    public static function markFailed(int $id): bool
    {
        return static::db()->execute(
            'UPDATE `payment_orders` SET `status` = ? WHERE `id` = ? AND `status` = ?',
            [self::STATUS_FAILED, $id, self::STATUS_CREATED]
        ) > 0;
    }

    /**
     * Every order with its buyer and package, newest first, for the admin
     * Orders page.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allWithDetails(int $limit = 500): array
    {
        return static::db()->fetchAll(
            'SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, p.name AS package_name
             FROM `payment_orders` o
             LEFT JOIN `users` u ON u.id = o.user_id
             LEFT JOIN `packages` p ON p.id = o.package_id
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT ' . max(1, $limit)
        );
    }
}
