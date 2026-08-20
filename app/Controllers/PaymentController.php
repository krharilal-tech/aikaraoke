<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\PaymentOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentService;
use RuntimeException;
use Throwable;

final class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    /**
     * Called via AJAX from the Pricing page (see public/assets/js/pricing.js)
     * — returns the Cashfree payment_session_id for the browser to hand to
     * their JS SDK, which is what actually triggers the redirect to
     * Cashfree's hosted checkout page (Cashfree doesn't offer a plain
     * server-side 302 for this, confirmed against their current docs).
     */
    public function checkout(Request $request): void
    {
        $this->requireCsrf($request);
        $this->rateLimit($request, 'payment_checkout', 10, 600);

        $userId = Auth::id();
        $packageId = Sanitizer::int($request->param('packageId'));
        $phone = preg_replace('/\D/', '', (string) $request->input('phone', ''));

        if ($phone === null || strlen($phone) !== 10) {
            $this->json(['success' => false, 'message' => 'Please enter a valid 10-digit phone number.'], 422);
        }

        $user = User::find($userId);

        if ($user === null) {
            $this->json(['success' => false, 'message' => 'Could not find your account.'], 404);
        }

        if ($user['phone'] !== $phone) {
            User::update($userId, ['phone' => $phone]);
        }

        try {
            $order = $this->paymentService->createOrder($userId, $packageId, $phone, (string) $user['email']);
        } catch (Throwable $e) {
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'Could not start the payment. Please try again.';
            $this->json(['success' => false, 'message' => $message], 500);
        }

        $this->json([
            'success' => true,
            'payment_session_id' => $order['payment_session_id'],
            'cashfree_mode' => Setting::get('cashfree_env', 'sandbox') === 'production' ? 'production' : 'sandbox',
        ]);
    }

    /**
     * Where Cashfree sends the user's browser back after checkout (GET,
     * order_id appended automatically — see order_meta.return_url in
     * PaymentService::createOrder()). The webhook is the source of truth,
     * but it can be delayed or lost, so this also actively checks Cashfree
     * directly rather than leaving the user looking at a stuck "processing"
     * page — PaymentService::confirmOrder() is the same idempotent path
     * either one uses, so whichever gets there first is the one that
     * actually grants credits.
     */
    public function returnCallback(Request $request): void
    {
        $cfOrderId = Sanitizer::string($request->input('order_id', ''), 64);
        $order = $cfOrderId !== '' ? PaymentOrder::findByCfOrderId($cfOrderId) : null;

        if ($order === null || (int) $order['user_id'] !== Auth::id()) {
            $this->view('payments/result', [
                'pageTitle' => 'Payment',
                'outcome' => 'not_found',
            ]);

            return;
        }

        if ($order['status'] === PaymentOrder::STATUS_CREATED) {
            $remoteOrder = $this->paymentService->fetchOrderStatus($cfOrderId);
            $remoteStatus = $remoteOrder['order_status'] ?? null;

            if ($remoteStatus === 'PAID') {
                $this->paymentService->confirmOrder($order, '');
                $order = PaymentOrder::find((int) $order['id']) ?? $order;
            } elseif (in_array($remoteStatus, ['EXPIRED', 'TERMINATED'], true)) {
                PaymentOrder::markFailed((int) $order['id']);
                $order = PaymentOrder::find((int) $order['id']) ?? $order;
            }
        }

        $outcome = match ($order['status']) {
            PaymentOrder::STATUS_PAID => 'success',
            PaymentOrder::STATUS_FAILED => 'failed',
            default => 'pending',
        };

        $this->view('payments/result', [
            'pageTitle' => 'Payment',
            'outcome' => $outcome,
            'credits' => (int) $order['credits'],
        ]);
    }

    /**
     * Server-to-server callback — never a user's browser, so no session/CSRF,
     * authenticated instead by Cashfree's HMAC signature. Mirrors
     * WorkerCallbackController::requireWorkerSecret()'s convention of
     * returning 404 (not 401/403) on auth failure.
     */
    public function webhook(Request $request): void
    {
        $this->requireCashfreeSignature($request);

        $this->paymentService->handleWebhookPayload($request->all());

        // Always 2xx once we've accepted and processed the request —
        // Cashfree retries on non-2xx, and retrying wouldn't change an
        // internal processing outcome (e.g. an order we don't recognize).
        $this->json(['success' => true]);
    }

    /**
     * Cashfree signs webhooks with the same Secret Key used to authenticate
     * outbound API calls — confirmed against their docs and reference repo;
     * there's no separate, dashboard-issued "webhook secret" the way some
     * other gateways (e.g. Stripe) do it.
     */
    private function requireCashfreeSignature(Request $request): void
    {
        $secret = env('CASHFREE_SECRET_KEY', '');
        $timestamp = $request->header('X-Webhook-Timestamp');
        $signature = $request->header('X-Webhook-Signature');

        if ($secret === '' || $timestamp === null || $signature === null) {
            Response::notFound();
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp . $request->rawBody(), $secret, true));

        if (!hash_equals($expected, $signature)) {
            Response::notFound();
        }
    }
}
