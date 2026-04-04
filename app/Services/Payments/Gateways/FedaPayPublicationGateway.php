<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PublicationPaymentGatewayInterface;
use App\DataTransferObjects\Payments\PublicationPaymentInitiateResult;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Services\FedaPayService;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FedaPayPublicationGateway implements PublicationPaymentGatewayInterface
{
    public const ID = 'fedapay';

    public function __construct(
        private readonly FedaPayService $fedapayService
    ) {}

    public function getId(): string
    {
        return self::ID;
    }

    public function getLabel(): string
    {
        return 'FedaPay';
    }

    public function getDescription(): string
    {
        return 'Carte bancaire, Mobile Money et autres moyens selon disponibilité (FedaPay).';
    }

    public function isConfigured(): bool
    {
        $key = config('fedapay.secret_key');

        return is_string($key) && $key !== '';
    }

    public function initiate(Listing $listing, User $user, string $returnTo): PublicationPaymentInitiateResult
    {
        $fee = (int) config('listing.publication_fee');
        if ($fee <= 0) {
            throw new \InvalidArgumentException('Montant de publication invalide.');
        }

        $merchantReference = 'listing-'.$listing->id.'-'.Str::lower(Str::uuid()->toString());

        $this->fedapayService->configure();

        Payment::query()
            ->where('listing_id', $listing->id)
            ->where('status', Payment::STATUS_PENDING)
            ->where('method', $this->getId())
            ->delete();

        $callbackUrl = $this->fedapayService->publicationCallbackUrl($this->getId());

        $name = trim((string) $user->name);
        $first = Str::before($name, ' ');
        $last = Str::contains($name, ' ') ? Str::after($name, ' ') : '-';
        if ($first === '') {
            $first = '-';
        }

        $customer = [
            'firstname' => $first,
            'lastname' => $last !== '' ? $last : '-',
            'email' => $user->email,
        ];

        try {
            $fedapayTransaction = Transaction::create([
                'description' => 'Publication annonce #'.$listing->id,
                'amount' => $fee,
                'currency' => ['iso' => config('fedapay.currency_iso', 'XOF')],
                'callback_url' => $callbackUrl,
                'merchant_reference' => $merchantReference,
                'custom_metadata' => [
                    'listing_id' => (string) $listing->id,
                    'return_to' => $returnTo,
                ],
                'customer' => $customer,
            ]);

            $tokenObj = $fedapayTransaction->generateToken();
            $paymentUrl = $tokenObj->url ?? null;
        } catch (Throwable $e) {
            Log::error('FedaPay initiate failed', ['exception' => $e]);
            throw $e;
        }

        if (! is_string($paymentUrl) || $paymentUrl === '') {
            throw new \RuntimeException('FedaPay did not return a payment URL.');
        }

        Payment::create([
            'listing_id' => $listing->id,
            'amount' => $fee,
            'status' => Payment::STATUS_PENDING,
            'method' => $this->getId(),
            'reference' => $merchantReference,
            'fedapay_transaction_id' => (string) $fedapayTransaction->id,
        ]);

        return new PublicationPaymentInitiateResult(
            paymentUrl: $paymentUrl,
            publicationFee: $fee,
            currency: (string) config('fedapay.currency_iso', 'XOF'),
            merchantReference: $merchantReference,
            returnTo: $returnTo,
            clientInitiated: false,
            cinetpay: null,
        );
    }

    public function handleCallback(Request $request): Response
    {
        $transactionId = $request->query('id');
        if ($transactionId === null || $transactionId === '') {
            return $this->returnStatusPage(
                'danger',
                'Retour incomplet',
                'Identifiant de transaction manquant. Fermez cet onglet et reprenez depuis l’application 2TONE.',
                'missing_id'
            );
        }

        try {
            if (! $this->isConfigured()) {
                return $this->returnStatusPage(
                    'danger',
                    'Configuration serveur',
                    'Le service de paiement n’est pas correctement configuré. Contactez le support.',
                    'config'
                );
            }
            $this->fedapayService->configure();
            $tx = Transaction::retrieve((string) $transactionId);
        } catch (Throwable $e) {
            Log::warning('FedaPay callback retrieve failed', ['exception' => $e]);

            return $this->returnStatusPage(
                'danger',
                'Vérification impossible',
                'Impossible de confirmer le statut auprès de FedaPay. Fermez cet onglet et vérifiez le paiement depuis l’application.',
                'retrieve'
            );
        }

        $payment = Payment::query()
            ->where('fedapay_transaction_id', (string) $tx->id)
            ->first();

        if (! $payment && isset($tx->merchant_reference)) {
            $payment = Payment::query()
                ->where('reference', (string) $tx->merchant_reference)
                ->first();
        }

        if (! $payment) {
            return $this->returnStatusPage(
                'danger',
                'Transaction introuvable',
                'Cette transaction n’est pas liée à une annonce de votre compte. Fermez cet onglet et reprenez depuis l’application.',
                'unknown_payment'
            );
        }

        $status = (string) $tx->status;

        if ($this->isSuccessfulTransactionStatus($status)) {
            if ($payment->status !== Payment::STATUS_COMPLETED) {
                $payment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                ]);
            }

            return $this->returnStatusPage(
                'success',
                'Paiement réussi',
                'Vous pouvez retourner à l’application !',
                $status
            );
        }

        [$tone, $title, $subtitle] = $this->mapFedapayStatusToUi($status);

        return $this->returnStatusPage(
            $tone,
            $title,
            $subtitle,
            $status
        );
    }

    private function returnStatusPage(
        string $tone,
        string $title,
        string $subtitle,
        ?string $statusCode = null
    ): Response {
        return response()
            ->view('payments.return-status', [
                'tone' => $tone,
                'title' => $title,
                'subtitle' => $subtitle,
                'statusCode' => $statusCode,
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function mapFedapayStatusToUi(string $status): array
    {
        return match (true) {
            in_array($status, ['canceled', 'cancelled'], true) => [
                'warning',
                'Paiement annulé',
                'Vous avez annulé ou interrompu le paiement. Vous pouvez relancer une tentative depuis l’application.',
            ],
            $status === 'declined' => [
                'warning',
                'Paiement refusé',
                'La transaction n’a pas été acceptée. Réessayez ou choisissez un autre moyen de paiement.',
            ],
            $status === 'pending' => [
                'info',
                'Paiement toujours en attente',
                'Le paiement n\'a pas été valider. Vous pouvez réessayer plus tard !',
            ],
            $status === 'expired' => [
                'warning',
                'Session expirée',
                'Le délai de paiement est dépassé. Lancez un nouveau paiement depuis l’application.',
            ],
            $status === 'refunded' => [
                'info',
                'Remboursement',
                'Cette transaction a été remboursée. Pour toute question, contactez le support.',
            ],
            default => [
                'warning',
                'Paiement non finalisé',
                'La transaction n’a pas abouti. Vous pouvez vérifier le statut ou réessayer depuis l’application.',
            ],
        };
    }

    private function isSuccessfulTransactionStatus(string $status): bool
    {
        return in_array($status, ['approved', 'transferred'], true);
    }
}
