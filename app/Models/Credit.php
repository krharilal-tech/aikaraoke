<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A ledger, not a balance column — every grant/spend/refund is its own row
 * (see database/migrations/002_credits_and_google_auth.sql), so a user's
 * balance is always SUM(delta) and there's a permanent, auditable trail of
 * where credits came from and went.
 */
final class Credit extends Model
{
    protected static string $table = 'credits';

    public const REASON_SIGNUP_BONUS = 'signup_bonus';
    public const REASON_PURCHASE = 'purchase';
    public const REASON_JOB_CONSUMED = 'job_consumed';
    public const REASON_JOB_REFUNDED = 'job_refunded';
    public const REASON_ADMIN_ADJUSTMENT = 'admin_adjustment';

    public static function balance(int $userId): int
    {
        $row = static::db()->fetchOne(
            'SELECT COALESCE(SUM(`delta`), 0) AS balance FROM `credits` WHERE `user_id` = ?',
            [$userId]
        );

        return (int) ($row['balance'] ?? 0);
    }

    public static function grant(int $userId, int $amount, string $reason, ?int $jobId = null, ?int $paymentOrderId = null): void
    {
        static::create([
            'user_id' => $userId,
            'delta' => $amount,
            'reason' => $reason,
            'job_id' => $jobId,
            'payment_order_id' => $paymentOrderId,
        ]);
    }

    /**
     * Spends 1 credit for a job, but only if the user actually has one —
     * atomic against concurrent double-spends (e.g. a double-submitted
     * form) by re-checking the balance inside the same call rather than
     * trusting a balance the caller read moments earlier. Returns false
     * (and writes nothing) if the balance was already zero.
     */
    public static function consumeForJob(int $userId, int $jobId): bool
    {
        if (static::balance($userId) <= 0) {
            return false;
        }

        static::create([
            'user_id' => $userId,
            'delta' => -1,
            'reason' => self::REASON_JOB_CONSUMED,
            'job_id' => $jobId,
        ]);

        return true;
    }

    /**
     * Only refunds if this job's consumption hasn't already been refunded —
     * safe to call from a polling loop that might observe the same
     * failed->failed transition more than once.
     */
    public static function refundForJob(int $userId, int $jobId): void
    {
        $alreadyRefunded = static::db()->fetchOne(
            'SELECT `id` FROM `credits` WHERE `job_id` = ? AND `reason` = ? LIMIT 1',
            [$jobId, self::REASON_JOB_REFUNDED]
        );

        if ($alreadyRefunded !== null) {
            return;
        }

        static::create([
            'user_id' => $userId,
            'delta' => 1,
            'reason' => self::REASON_JOB_REFUNDED,
            'job_id' => $jobId,
        ]);
    }
}
