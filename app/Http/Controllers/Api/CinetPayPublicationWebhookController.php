<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\CinetPayApiService;
use App\Services\Payments\Gateways\CinetPayPublicationGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CinetPayPublicationWebhookController extends Controller
{
    public function __construct(
        private readonly CinetPayApiService $cinetPayApi
    ) {}

    /**
     * Notification CinetPay (API v1) — JSON avec notify_token, transaction_id, merchant_transaction_id.
     */
    public function publication(Request $request): Response
    {
        $payload = $request->all();
        $notifyToken = $payload['notify_token'] ?? null;
        $transactionId = $payload['transaction_id'] ?? null;
        $merchantTransactionId = $payload['merchant_transaction_id'] ?? null;

        if (! is_string($notifyToken) || $notifyToken === ''
            || ! is_string($transactionId) || $transactionId === ''
            || ! is_string($merchantTransactionId) || $merchantTransactionId === '') {
            return response('Invalid payload', 400);
        }

        $payment = Payment::query()
            ->where('reference', $merchantTransactionId)
            ->where('method', CinetPayPublicationGateway::ID)
            ->first();

        if (! $payment) {
            Log::warning('CinetPay webhook: payment not found', ['merchant' => $merchantTransactionId]);

            return response('OK', 200);
        }

        if ($payment->status === Payment::STATUS_COMPLETED) {
            return response('OK', 200);
        }

        $expected = $payment->cinetpay_notify_token;
        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $notifyToken)) {
            Log::warning('CinetPay webhook: notify_token mismatch', ['payment_id' => $payment->id]);

            return response('Unauthorized', 401);
        }

        $status = $this->resolveTransactionStatus($transactionId, $payload);

        if ($status === null) {
            Log::warning('CinetPay webhook: could not resolve transaction status', [
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
            ]);

            return response('OK', 200);
        }

        if ($status === 'SUCCESS') {
            if ($payment->status !== Payment::STATUS_COMPLETED) {
                $payment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                    'cinetpay_transaction_id' => $transactionId,
                ]);
            }
        } elseif (in_array($status, ['FAILED', 'TRANSACTION_EXIST', 'INSUFFICIENT_BALANCE'], true)) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'cinetpay_transaction_id' => $transactionId,
            ]);
        }

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTransactionStatus(string $transactionId, array $payload): ?string
    {
        if ($this->cinetPayApi->isConfigured()) {
            $json = $this->cinetPayApi->getPaymentStatus($transactionId);
            if (is_array($json) && isset($json['status']) && is_string($json['status'])) {
                return $json['status'];
            }
        }

        $fromBody = $payload['status'] ?? null;

        return is_string($fromBody) && $fromBody !== '' ? $fromBody : null;
    }
}
