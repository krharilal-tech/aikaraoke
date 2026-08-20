<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PaymentOrder extends Model
{
    protected static string $table = 'payment_orders';

    public const STATUS_CREATED = 'created';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, mixed>|null
     */
    public static function findByCfOrderId(string $cfOrderId): ?array
    {
        return static::firstWhere(['cf_order_id' => $cfOrderId]);
    }

    public static function markPaid(int $id, string $cfPaymentId): void
    {
        static::update($id, [
            'status' => self::STATUS_PAID,
            'cf_payment_id' => $cfPaymentId,
        ]);
    }

    /**
     * Only transitions an order that's still `created` — an order already
     * marked paid must never be downgraded by a late or duplicate failure
     * event (webhooks can arrive out of order or more than once).
     */
    public static function markFailed(int $id): void
    {
        static::db()->execute(
            'UPDATE `payment_orders` SET `status` = ? WHERE `id` = ? AND `status` = ?',
            [self::STATUS_FAILED, $id, self::STATUS_CREATED]
        );
    }
}
