<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Services\FedaPayService;
use FedaPay\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        if (!$listing) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }
        if ((int) $listing->owner_id !== (int) $user->id && !$user->isAdmin()) {
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

        if (!config('fedapay.secret_key')) {
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
        if (!in_array($returnTo, ['create', 'edit'], true)) {
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

        if (!is_string($paymentUrl) || $paymentUrl === '') {
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
     */
    public function fedapayCallback(Request $request): RedirectResponse
    {
        $transactionId = $request->query('id');
        if ($transactionId === null || $transactionId === '') {
            return redirect()->away('2tone://listing-payment?error=missing_id');
        }

        try {
            if (!config('fedapay.secret_key')) {
                return redirect()->away('2tone://listing-payment?error=config');
            }
            $this->fedapayService->configure();
            $tx = Transaction::retrieve((string) $transactionId);
        } catch (Throwable $e) {
            Log::warning('FedaPay callback retrieve failed', ['exception' => $e]);

            return redirect()->away('2tone://listing-payment?error=retrieve');
        }

        $payment = Payment::query()
            ->where('fedapay_transaction_id', (string) $tx->id)
            ->first();

        if (!$payment && isset($tx->merchant_reference)) {
            $payment = Payment::query()
                ->where('reference', (string) $tx->merchant_reference)
                ->first();
        }

        if (!$payment) {
            return redirect()->away('2tone://listing-payment?error=unknown_payment');
        }

        $listingId = $payment->listing_id;

        $returnTo = $this->resolveReturnTo($tx);

        if ($this->isSuccessfulTransactionStatus((string) $tx->status)) {
            if ($payment->status !== Payment::STATUS_COMPLETED) {
                $payment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                ]);
            }

            return redirect()->away(
                '2tone://listing-payment?'.http_build_query([
                    'listing_id' => $listingId,
                    'paid' => '1',
                    'to' => $returnTo,
                ])
            );
        }

        return redirect()->away(
            '2tone://listing-payment?'.http_build_query([
                'listing_id' => $listingId,
                'paid' => '0',
                'to' => $returnTo,
            ])
        );
    }

    private function resolveReturnTo(object $tx): string
    {
        $meta = $tx->custom_metadata ?? null;
        if (is_object($meta) && isset($meta->return_to)) {
            $v = (string) $meta->return_to;

            return in_array($v, ['create', 'edit'], true) ? $v : 'create';
        }
        if (is_array($meta) && isset($meta['return_to'])) {
            $v = (string) $meta['return_to'];

            return in_array($v, ['create', 'edit'], true) ? $v : 'create';
        }

        return 'create';
    }

    /**
     * Statut de paiement pour une annonce (propriétaire).
     */
    public function status(Request $request, int $listingId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $listing = Listing::query()->find($listingId);
        if (!$listing) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }
        if ((int) $listing->owner_id !== (int) $user->id && !$user->isAdmin()) {
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
