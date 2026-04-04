<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PublicationPaymentGatewayInterface;
use App\DataTransferObjects\Payments\PublicationPaymentInitiateResult;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CinetPayPublicationGateway implements PublicationPaymentGatewayInterface
{
    public const ID = 'cinetpay';

    public function getId(): string
    {
        return self::ID;
    }

    public function getLabel(): string
    {
        return 'CinetPay';
    }

    public function getDescription(): string
    {
        return 'Mobile Money et autres moyens (CinetPay).';
    }

    public function isConfigured(): bool
    {
        if (! filter_var(config('cinetpay.publication_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }
        $base = rtrim((string) config('fedapay.callback_base_url', config('app.url')), '/');

        return $base !== '';
    }

    public function initiate(Listing $listing, User $user, string $returnTo): PublicationPaymentInitiateResult
    {
        $fee = (int) config('listing.publication_fee');
        if ($fee <= 0) {
            throw new \InvalidArgumentException('Montant de publication invalide.');
        }

        $merchantReference = $this->makeMerchantTransactionId((int) $listing->id);

        Payment::query()
            ->where('listing_id', $listing->id)
            ->where('status', Payment::STATUS_PENDING)
            ->where('method', $this->getId())
            ->delete();

        $base = rtrim((string) config('fedapay.callback_base_url', config('app.url')), '/');
        $notifyUrl = $base.'/api/webhooks/cinetpay/publication';
        $returnUrl = $base.'/api/payments/callback/'.$this->getId();
        $successUrl = $returnUrl;
        $failedUrl = $returnUrl;

        $currency = (string) config('cinetpay.currency', config('fedapay.currency_iso', 'XOF'));

        Payment::create([
            'listing_id' => $listing->id,
            'amount' => $fee,
            'status' => Payment::STATUS_PENDING,
            'method' => $this->getId(),
            'reference' => $merchantReference,
        ]);

        return new PublicationPaymentInitiateResult(
            paymentUrl: null,
            publicationFee: $fee,
            currency: $currency,
            merchantReference: $merchantReference,
            returnTo: $returnTo,
            clientInitiated: true,
            cinetpay: [
                'notify_url' => $notifyUrl,
                'success_url' => $successUrl,
                'failed_url' => $failedUrl,
                'country' => strtoupper((string) config('cinetpay.country', 'ML')),
                'return_to' => $returnTo,
            ],
        );
    }

    public function handleCallback(Request $request): Response
    {
        return response()
            ->view('payments.return-status', [
                'tone' => 'info',
                'title' => 'Retour à l’application',
                'subtitle' => 'Vous pouvez fermer cette page et vérifier le statut du paiement dans 2TONE.',
                'statusCode' => null,
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Identifiant marchand CinetPay (1–30 caractères).
     */
    private function makeMerchantTransactionId(int $listingId): string
    {
        $raw = 'p'.$listingId.'-'.Str::lower(Str::random(12));

        return Str::limit($raw, 30, '');
    }
}
