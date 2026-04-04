<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Appels minimaux à l’API CinetPay v1 (OAuth + statut paiement) pour le webhook de publication.
 */
class CinetPayApiService
{
    public function resolveBaseUrl(): string
    {
        $key = config('cinetpay.api_key');
        if (is_string($key) && str_starts_with($key, 'sk_live_')) {
            return 'https://api.cinetpay.co';
        }

        return 'https://api.cinetpay.net';
    }

    public function isConfigured(): bool
    {
        $k = config('cinetpay.api_key');
        $p = config('cinetpay.api_password');

        return is_string($k) && $k !== '' && is_string($p) && $p !== '';
    }

    /**
     * @return array{code?: int, status?: string, merchant_transaction_id?: string, transaction_id?: string}|null
     */
    public function getPaymentStatus(string $identifier): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $base = $this->resolveBaseUrl();
        try {
            $token = $this->getAccessToken();
            $response = Http::timeout(30)
                ->withToken($token)
                ->acceptJson()
                ->get($base.'/v1/payment/'.rawurlencode($identifier));
            if (! $response->successful()) {
                Log::warning('CinetPay getPaymentStatus HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::warning('CinetPay getPaymentStatus failed', ['exception' => $e]);

            return null;
        }
    }

    private function getAccessToken(): string
    {
        $base = $this->resolveBaseUrl();
        $response = Http::timeout(30)->asJson()->post($base.'/v1/oauth/login', [
            'api_key' => config('cinetpay.api_key'),
            'api_password' => config('cinetpay.api_password'),
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('CinetPay OAuth failed: HTTP '.$response->status());
        }
        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('CinetPay OAuth: missing access_token');
        }

        return $token;
    }
}
