<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * Fire-and-forget "something just happened" emails to the site operator, so
 * activity (signups, purchases, new jobs) can be monitored from an inbox
 * without watching the database.
 *
 * Every method is best-effort: a mail failure is logged and swallowed, never
 * propagated into the user-facing flow that triggered it.
 *
 * Recipients: ADMIN_ALERT_EMAIL (comma-separated) when set, otherwise every
 * `users` row with role='admin'. Turn the whole thing off with
 * ADMIN_ALERTS=false, or by leaving MAIL_HOST unconfigured.
 */
final class AdminNotifier
{
    public static function userRegistered(int $userId, string $email, ?string $name, string $method): void
    {
        self::dispatch(
            'New signup: ' . $email,
            '<p>A new account was just created.</p>' . self::table([
                'Name' => $name ?? '—',
                'Email' => $email,
                'User ID' => (string) $userId,
                'Method' => $method,
                'When (UTC)' => gmdate('Y-m-d H:i:s'),
            ])
        );
    }

    /**
     * @param array<string, mixed> $order a payment_orders row
     */
    public static function purchaseCompleted(array $order, string $email, ?string $packageName): void
    {
        self::dispatch(
            'Purchase: ' . $email . ' bought ' . (int) $order['credits'] . ' credits',
            '<p>A credit purchase just completed.</p>' . self::table([
                'Email' => $email,
                'Package' => $packageName ?? ('#' . ($order['package_id'] ?? '?')),
                'Credits' => (string) (int) $order['credits'],
                'Amount' => 'INR ' . number_format((float) $order['amount_inr'], 2),
                'Order ID' => (string) ($order['cf_order_id'] ?? $order['id'] ?? '?'),
                'User ID' => (string) ($order['user_id'] ?? '?'),
                'When (UTC)' => gmdate('Y-m-d H:i:s'),
            ])
        );
    }

    /**
     * The user clicked "Buy Now" (they may or may not go on to pay).
     *
     * @param array<string, mixed> $order a payment_orders row
     * @param array<string, mixed> $buyer a users row
     */
    public static function checkoutStarted(array $order, array $buyer, ?string $packageName): void
    {
        $email = (string) ($buyer['email'] ?? 'unknown');

        self::dispatch(
            'Buy Now: ' . $email . ' is looking at ' . ($packageName ?? 'a package'),
            '<p>A user clicked <strong>Buy Now</strong>. You will get another email if the payment succeeds or fails.</p>'
            . self::orderTable($order, $buyer, $packageName)
            . self::ordersLink()
        );
    }

    /**
     * @param array<string, mixed> $order a payment_orders row
     * @param array<string, mixed> $buyer a users row
     */
    public static function purchaseFailed(array $order, array $buyer, ?string $packageName, string $reason): void
    {
        $email = (string) ($buyer['email'] ?? 'unknown');

        self::dispatch(
            'Payment failed: ' . $email . ' (' . ($packageName ?? 'package') . ')',
            '<p>A payment did not go through.</p>'
            . self::orderTable($order, $buyer, $packageName, ['Reason' => $reason])
            . self::ordersLink()
        );
    }

    /**
     * @param array<string, mixed> $job a jobs row (or a minimal stand-in)
     */
    public static function jobCreated(array $job, ?string $email): void
    {
        $jobId = (int) ($job['id'] ?? 0);

        self::dispatch(
            'New karaoke job #' . $jobId . ($email !== null ? ' by ' . $email : ''),
            '<p>A karaoke job was just started.</p>' . self::table([
                'Job ID' => (string) $jobId,
                'User' => $email ?? 'guest',
                'YouTube URL' => (string) ($job['youtube_url'] ?? '—'),
                'Keep vocals' => ((int) ($job['keep_vocals'] ?? 0) === 1) ? 'yes' : 'no',
                'When (UTC)' => gmdate('Y-m-d H:i:s'),
            ]) . '<p><a href="' . e(base_url('jobs/' . $jobId)) . '">Open job</a></p>'
        );
    }

    private static function dispatch(string $subject, string $htmlBody): void
    {
        try {
            if (!filter_var((string) env('ADMIN_ALERTS', 'true'), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            $mailer = new Mailer();

            if (!$mailer->isConfigured()) {
                return;
            }

            $recipients = self::recipients();

            if ($recipients === []) {
                Logger::warning('AdminNotifier: no recipient could be resolved', ['subject' => $subject]);

                return;
            }

            $prefixed = '[' . config('app.name', 'AI Karaoke') . '] ' . $subject;

            foreach ($recipients as $to) {
                $mailer->send($to, 'Admin', $prefixed, $htmlBody);
            }
        } catch (Throwable $e) {
            Logger::error('AdminNotifier failed', ['subject' => $subject, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function recipients(): array
    {
        $configured = trim((string) env('ADMIN_ALERT_EMAIL', ''));

        if ($configured !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $configured))));
        }

        try {
            $rows = Database::instance()->fetchAll("SELECT `email` FROM `users` WHERE `role` = 'admin'");

            return array_values(array_filter(array_column($rows, 'email')));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $buyer
     * @param array<string, string> $extra
     */
    private static function orderTable(array $order, array $buyer, ?string $packageName, array $extra = []): string
    {
        return self::table(array_merge([
            'Name' => (string) ($buyer['name'] ?? '—'),
            'Email' => (string) ($buyer['email'] ?? '—'),
            'Phone' => (string) ($order['customer_phone'] ?? $buyer['phone'] ?? '—'),
            'Package' => $packageName ?? ('#' . ($order['package_id'] ?? '?')),
            'Amount' => 'INR ' . number_format((float) ($order['amount_inr'] ?? 0), 2),
            'Credits' => (string) (int) ($order['credits'] ?? 0),
            'Order ID' => (string) ($order['cf_order_id'] ?? $order['id'] ?? '?'),
        ], $extra, [
            'When (UTC)' => gmdate('Y-m-d H:i:s'),
        ]));
    }

    private static function ordersLink(): string
    {
        return '<p><a href="' . e(base_url('admin/orders')) . '">Open Orders</a></p>';
    }

    /**
     * @param array<string, string> $rows
     */
    private static function table(array $rows): string
    {
        $html = '<table cellpadding="6" style="border-collapse:collapse; font-family:sans-serif;">';

        foreach ($rows as $label => $value) {
            $html .= '<tr>'
                . '<td style="border:1px solid #ddd;"><strong>' . e($label) . '</strong></td>'
                . '<td style="border:1px solid #ddd;">' . e($value) . '</td>'
                . '</tr>';
        }

        return $html . '</table>';
    }
}
