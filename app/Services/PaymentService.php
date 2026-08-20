<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\Credit;
use App\Models\Package;
use App\Models\PaymentOrder;
use App\Models\Setting;
use RuntimeException;

/**
 * Cashfree integration: creating an order (redirect-based hosted checkout,
 * not the embedded drop-in) and reconciling payment outcomes. No HTTP
 * client library exists in this codebase (composer.json only has
 * phpmailer) — outbound calls use raw curl, same as
 * JobService::dispatchToRunPod().
 */
final class PaymentService
{
    private const API_VERSION = '2026-01-01';

    public function createOrder(int $userId, int $packageId, string $customerPhone, string $customerEmail): array
    {
        $package = Package::find($packageId);

        if ($package === null || (int) $package['is_active'] !== 1) {
            throw new RuntimeException('That package is not available.');
        }

        $cfOrderId = 'ord_' . bin2hex(random_bytes(8));

        $orderRowId = (int) PaymentOrder::create([
            'user_id' => $userId,
            'package_id' => $packageId,
            'cf_order_id' => $cfOrderId,
            'amount_inr' => $package['price_inr'],
            'credits' => $package['credits'],
            'status' => PaymentOrder::STATUS_CREATED,
        ]);

        $payload = [
            'order_id' => $cfOrderId,
            'order_amount' => (float) $package['price_inr'],
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id' => 'user_' . $userId,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
            ],
            // Cashfree appends "?order_id=..." to this itself on redirect
            // back — no placeholder syntax to fill in here.
            'order_meta' => [
                'return_url' => base_url('payments/return'),
            ],
        ];

        $response = $this->request('POST', '/orders', $payload);

        if ($response === null || !isset($response['payment_session_id'])) {
            PaymentOrder::markFailed($orderRowId);

            Logger::error('Cashfree order creation failed', [
                'order_row_id' => $orderRowId,
                'cf_order_id' => $cfOrderId,
                'response' => $response,
            ]);

            throw new RuntimeException('Could not start the payment. Please try again.');
        }

        return [
            'payment_session_id' => $response['payment_session_id'],
            'cf_order_id' => $cfOrderId,
        ];
    }

    /**
     * The single idempotent "mark paid + grant credits" path — called from
     * both the webhook and PaymentController::returnCallback()'s fallback
     * status check, so whichever of the two arrives first is the one that
     * actually grants credits. A no-op if the order isn't still `created`
     * (already paid, or already failed).
     *
     * @param array<string, mixed> $order
     */
    public function confirmOrder(array $order, string $cfPaymentId): void
    {
        if ($order['status'] !== PaymentOrder::STATUS_CREATED) {
            // Already resolved (e.g. the return-page fallback beat the
            // webhook here) — backfill cf_payment_id if this call has one
            // and the stored order doesn't, but never re-grant credits.
            if ($cfPaymentId !== '' && empty($order['cf_payment_id'])) {
                PaymentOrder::markPaid((int) $order['id'], $cfPaymentId);
            }

            return;
        }

        PaymentOrder::markPaid((int) $order['id'], $cfPaymentId);

        Credit::grant(
            (int) $order['user_id'],
            (int) $order['credits'],
            Credit::REASON_PURCHASE,
            null,
            (int) $order['id']
        );

        Logger::info('Credits granted for Cashfree purchase', [
            'payment_order_id' => $order['id'],
            'user_id' => $order['user_id'],
            'credits' => $order['credits'],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleWebhookPayload(array $payload): void
    {
        $cfOrderId = $payload['data']['order']['order_id'] ?? null;
        $paymentStatus = $payload['data']['payment']['payment_status'] ?? null;
        $cfPaymentId = $payload['data']['payment']['cf_payment_id'] ?? null;

        if (!is_string($cfOrderId)) {
            Logger::error('Cashfree webhook missing order_id', ['payload' => $payload]);

            return;
        }

        $order = PaymentOrder::findByCfOrderId($cfOrderId);

        if ($order === null) {
            // Retrying won't fix an order we don't recognize — nothing more
            // to do, and the controller still returns 2xx so Cashfree stops
            // redelivering it.
            Logger::error('Cashfree webhook for unknown order', ['cf_order_id' => $cfOrderId]);

            return;
        }

        if ($paymentStatus === 'SUCCESS') {
            $this->confirmOrder($order, is_string($cfPaymentId) ? $cfPaymentId : '');

            return;
        }

        PaymentOrder::markFailed((int) $order['id']);
    }

    /**
     * Used by PaymentController::returnCallback() as a fallback when the
     * user lands back before the webhook has arrived — queries Cashfree
     * directly rather than leaving the order stuck showing "processing"
     * indefinitely if a webhook is delayed or lost.
     */
    public function fetchOrderStatus(string $cfOrderId): ?array
    {
        return $this->request('GET', '/orders/' . rawurlencode($cfOrderId));
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|null null on any transport/HTTP failure
     */
    private function request(string $method, string $path, ?array $body = null): ?array
    {
        $env = Setting::get('cashfree_env', 'sandbox');
        $base = $env === 'production' ? 'https://api.cashfree.com/pg' : 'https://sandbox.cashfree.com/pg';

        $ch = curl_init($base . $path);

        $headers = [
            'x-client-id: ' . env('CASHFREE_APP_ID', ''),
            'x-client-secret: ' . env('CASHFREE_SECRET_KEY', ''),
            'x-api-version: ' . self::API_VERSION,
            'Content-Type: application/json',
        ];

        $options = [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpStatus < 200 || $httpStatus >= 300) {
            Logger::error('Cashfree API call failed', [
                'method' => $method,
                'path' => $path,
                'http_status' => $httpStatus,
                'curl_error' => $curlError,
            ]);

            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
