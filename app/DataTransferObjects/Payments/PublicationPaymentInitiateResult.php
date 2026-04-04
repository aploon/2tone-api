<?php

namespace App\DataTransferObjects\Payments;

final readonly class PublicationPaymentInitiateResult
{
    /**
     * @param  array<string, mixed>|null  $cinetpay  Métadonnées pour initialisation côté app (cinetpay-js).
     */
    public function __construct(
        public ?string $paymentUrl,
        public int $publicationFee,
        public string $currency,
        public string $merchantReference,
        public string $returnTo,
        public bool $clientInitiated = false,
        public ?array $cinetpay = null,
    ) {}
}
