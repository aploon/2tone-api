<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PublicationPaymentGatewayRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class ListingPublicationPaymentController extends Controller
{
    public function __construct(
        private readonly PublicationPaymentGatewayRegistry $gatewayRegistry
    ) {}

    /**
     * Moyens de paiement disponibles pour les frais de publication (extensible).
     */
    public function methods(Request $request, int $listingId): JsonResponse
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

        if ($user->status === User::STATUS_PENDING_OTP) {
            return response()->json(['message' => 'Confirmez votre numéro de téléphone avant de payer.'], 403);
        }

        $paid = $listing->hasCompletedPublicationPayment();
        $fee = (int) config('listing.publication_fee');
        $currency = (string) config('cinetpay.currency', config('fedapay.currency_iso', 'XOF'));

        $methods = [];
        foreach ($this->gatewayRegistry->all() as $gateway) {
            $methods[] = [
                'id' => $gateway->getId(),
                'label' => $gateway->getLabel(),
                'description' => $gateway->getDescription(),
                'available' => $gateway->isConfigured(),
            ];
        }

        return response()->json([
            'already_paid' => $paid,
            'publication_fee' => $fee,
            'currency' => $currency,
            'methods' => $methods,
        ]);
    }

    /**
     * Démarre un paiement via la passerelle choisie (`gateway` dans le corps JSON).
     */
    public function initiate(Request $request, int $listingId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'gateway' => ['required', 'string'],
            'return_to' => ['sometimes', 'string', 'in:create,edit'],
        ]);

        $returnTo = $data['return_to'] ?? 'create';

        $listing = Listing::query()->find($listingId);
        if (! $listing) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }
        if ((int) $listing->owner_id !== (int) $user->id && ! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->status === User::STATUS_PENDING_OTP) {
            return response()->json(['message' => 'Confirmez votre numéro de téléphone avant de payer.'], 403);
        }

        if ($listing->hasCompletedPublicationPayment()) {
            return response()->json([
                'already_paid' => true,
                'payment_url' => null,
                'client_initiated' => false,
                'cinetpay' => null,
                'gateway' => $data['gateway'],
                'publication_fee' => (int) config('listing.publication_fee'),
                'currency' => config('cinetpay.currency', config('fedapay.currency_iso', 'XOF')),
            ]);
        }

        $fee = (int) config('listing.publication_fee');
        if ($fee <= 0) {
            return response()->json(['message' => 'Montant de publication invalide.'], 422);
        }

        $gateway = $this->gatewayRegistry->get($data['gateway']);
        if ($gateway === null) {
            return response()->json(['message' => 'Moyen de paiement inconnu.'], 422);
        }
        if (! $gateway->isConfigured()) {
            return response()->json(['message' => 'Ce moyen de paiement n’est pas disponible (configuration).'], 503);
        }

        try {
            $result = $gateway->initiate($listing, $user, $returnTo);
        } catch (Throwable $e) {
            Log::error('Publication payment initiate failed', [
                'gateway' => $data['gateway'],
                'exception' => $e,
            ]);

            return response()->json(['message' => 'Impossible de démarrer le paiement.'], 502);
        }

        return response()->json([
            'already_paid' => false,
            'payment_url' => $result->paymentUrl,
            'client_initiated' => $result->clientInitiated,
            'cinetpay' => $result->cinetpay,
            'gateway' => $gateway->getId(),
            'publication_fee' => $result->publicationFee,
            'currency' => $result->currency,
            'merchant_reference' => $result->merchantReference,
            'return_to' => $result->returnTo,
        ]);
    }

    /**
     * Après initialisation CinetPay côté app : enregistre notify_token / transaction_id pour le webhook.
     */
    public function cinetpayAck(Request $request, int $listingId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'merchant_reference' => ['required', 'string', 'max:100'],
            'notify_token' => ['required', 'string'],
            'transaction_id' => ['required', 'string', 'max:120'],
        ]);

        $listing = Listing::query()->find($listingId);
        if (! $listing) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }
        if ((int) $listing->owner_id !== (int) $user->id && ! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->status === User::STATUS_PENDING_OTP) {
            return response()->json(['message' => 'Confirmez votre numéro de téléphone avant de payer.'], 403);
        }

        $payment = Payment::query()
            ->where('listing_id', $listing->id)
            ->where('reference', $data['merchant_reference'])
            ->where('method', 'cinetpay')
            ->where('status', Payment::STATUS_PENDING)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Paiement introuvable ou déjà traité.'], 404);
        }

        $payment->update([
            'cinetpay_notify_token' => $data['notify_token'],
            'cinetpay_transaction_id' => $data['transaction_id'],
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Retour utilisateur après redirection prestataire (route par passerelle).
     */
    public function callback(Request $request, string $gatewayId): Response
    {
        $gateway = $this->gatewayRegistry->get($gatewayId);
        if ($gateway === null) {
            abort(404);
        }

        return $gateway->handleCallback($request);
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

        if ($user->status === User::STATUS_PENDING_OTP) {
            return response()->json(['message' => 'Confirmez votre numéro de téléphone avant de payer.'], 403);
        }

        $paid = $listing->hasCompletedPublicationPayment();

        return response()->json([
            'paid' => $paid,
            'publication_fee' => (int) config('listing.publication_fee'),
            'currency' => config('cinetpay.currency', config('fedapay.currency_iso', 'XOF')),
        ]);
    }
}
