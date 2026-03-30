<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Services\FedaPayService;
use FedaPay\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ListingPublicationPaymentController extends Controller
{
    public function __construct(
        private readonly FedaPayService $fedapayService
    ) {}

    /**
     * Démarre un paiement FedaPay (redirection vers la page de collecte).
     */
    public function initiate(Request $request, int $listingId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $listing = Listing::query()->find($listingId);
        if (! $listing) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }
        if ((int) $listing->owner_id !== (int) $user->id && ! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($listing->hasCompletedPublicationPayment()) {
            return response()->json([
                'already_paid' => true,
                'payment_url' => null,
                'publication_fee' => (int) config('listing.publication_fee'),
                'currency' => config('fedapay.currency_iso'),
            ]);
        }

        if (! config('fedapay.secret_key')) {
            return response()->json(['message' => 'Paiement indisponible (configuration).'], 503);
        }

        $fee = (int) config('listing.publication_fee');
        if ($fee <= 0) {
            return response()->json(['message' => 'Montant de publication invalide.'], 422);
        }

        $merchantReference = 'listing-'.$listing->id.'-'.Str::lower(Str::uuid()->toString());

        try {
            $this->fedapayService->configure();
        } catch (Throwable $e) {
            Log::error('FedaPay configure failed', ['exception' => $e]);

            return response()->json(['message' => 'Paiement indisponible.'], 503);
        }

        Payment::query()
            ->where('listing_id', $listing->id)
            ->where('status', Payment::STATUS_PENDING)
            ->where('method', 'fedapay')
            ->delete();

        $callbackUrl = $this->fedapayService->callbackUrl();

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

        $returnTo = $request->input('return_to', 'create');
        if (! in_array($returnTo, ['create', 'edit'], true)) {
            $returnTo = 'create';
        }

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

            return response()->json(['message' => 'Impossible de démarrer le paiement.'], 502);
        }

        if (! is_string($paymentUrl) || $paymentUrl === '') {
            return response()->json(['message' => 'Impossible de démarrer le paiement.'], 502);
        }

        Payment::create([
            'listing_id' => $listing->id,
            'amount' => $fee,
            'status' => Payment::STATUS_PENDING,
            'method' => 'fedapay',
            'reference' => $merchantReference,
            'fedapay_transaction_id' => (string) $fedapayTransaction->id,
        ]);

        return response()->json([
            'already_paid' => false,
            'payment_url' => $paymentUrl,
            'publication_fee' => $fee,
            'currency' => config('fedapay.currency_iso'),
            'merchant_reference' => $merchantReference,
            'return_to' => $returnTo,
        ]);
    }

    /**
     * Retour utilisateur après redirection FedaPay (ne pas se fier seul au statut query : vérification API).
     * Affiche une page HTML avec le résultat (l’app reprend le premier plan après WebBrowser).
     */
    public function fedapayCallback(Request $request): Response
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
            if (! config('fedapay.secret_key')) {
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
            ->view('fedapay.return-status', [
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

    /**
     * Statut de paiement pour une annonce (propriétaire).
     */
    public function status(Request $request, int $listingId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $listing = Listing::query()->find($listingId);
        if (! $listing) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }
        if ((int) $listing->owner_id !== (int) $user->id && ! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $paid = $listing->hasCompletedPublicationPayment();

        return response()->json([
            'paid' => $paid,
            'publication_fee' => (int) config('listing.publication_fee'),
            'currency' => config('fedapay.currency_iso'),
        ]);
    }

    private function isSuccessfulTransactionStatus(string $status): bool
    {
        return in_array($status, ['approved', 'transferred'], true);
    }
}
