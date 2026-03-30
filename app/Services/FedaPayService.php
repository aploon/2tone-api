<?php

namespace App\Services;

use FedaPay\FedaPay;
use InvalidArgumentException;

class FedaPayService
{
    public function configure(): void
    {
        $key = config('fedapay.secret_key');
        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('FEDAPAY_SECRET_KEY is not configured.');
        }
        FedaPay::setApiKey($key);
        FedaPay::setEnvironment(config('fedapay.environment', 'sandbox'));
    }

    /**
     * URL de retour HTTP après paiement (une route par passerelle : /api/payments/callback/{gateway}).
     */
    public function publicationCallbackUrl(string $gatewayId): string
    {
        $base = rtrim((string) config('fedapay.callback_base_url', config('app.url')), '/');

        return $base.'/api/payments/callback/'.$gatewayId;
    }
}
