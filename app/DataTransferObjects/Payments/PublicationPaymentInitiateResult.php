<?php

namespace App\DataTransferObjects\Payments;

final readonly class PublicationPaymentInitiateResult
{
    public function __construct(
        public string $paymentUrl,
        public int $publicationFee,
        public string $currency,
        public string $merchantReference,
        public string $returnTo,
    ) {}
}
