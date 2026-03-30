<?php

namespace App\Contracts\Payments;

use App\DataTransferObjects\Payments\PublicationPaymentInitiateResult;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Passerelle de paiement pour les frais de publication d’une annonce (extensible : FedaPay, Stripe, etc.).
 */
interface PublicationPaymentGatewayInterface
{
    /** Identifiant stable (stocké en `payments.method`, utilisé dans les URLs de callback). */
    public function getId(): string;

    public function getLabel(): string;

    /** Texte court pour l’UI (liste des moyens de paiement). */
    public function getDescription(): string;

    public function isConfigured(): bool;

    /**
     * Crée la transaction côté prestataire et l’enregistrement `Payment` en attente.
     *
     * @throws \Throwable en cas d’échec API prestataire
     */
    public function initiate(Listing $listing, User $user, string $returnTo): PublicationPaymentInitiateResult;

    /**
     * Retour HTTP (souvent page HTML) après redirection utilisateur depuis le prestataire.
     */
    public function handleCallback(Request $request): Response;
}
