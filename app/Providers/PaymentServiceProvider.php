<?php

namespace App\Providers;

use App\Services\Payments\Gateways\CinetPayPublicationGateway;
use App\Services\Payments\Gateways\FedaPayPublicationGateway;
use App\Services\Payments\PublicationPaymentGatewayRegistry;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PublicationPaymentGatewayRegistry::class, function ($app) {
            return new PublicationPaymentGatewayRegistry([
                $app->make(FedaPayPublicationGateway::class),
                $app->make(CinetPayPublicationGateway::class),
            ]);
        });
    }
}
