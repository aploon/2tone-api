# Paiements des frais de publication (annonces)

Ce document décrit l’architecture des passerelles de paiement, les endpoints API, la configuration (FedaPay) et les étapes pour ajouter un nouveau prestataire.

## Vue d’ensemble

- Les frais de publication sont gérés via un **registre de passerelles** (`PublicationPaymentGatewayRegistry`). Chaque prestataire implémente `PublicationPaymentGatewayInterface`.
- L’application mobile **ne choisit plus un prestataire par défaut** : l’utilisateur appelle d’abord la liste des moyens disponibles, puis **initie** le paiement avec un identifiant de passerelle (`gateway`).
- Le **callback HTTP** après redirection utilisateur est générique : `GET /api/payments/callback/{gateway}` (ex. `fedapay`).

## Fichiers clés (API)

| Rôle | Fichier |
|------|---------|
| Contrat | `app/Contracts/Payments/PublicationPaymentGatewayInterface.php` |
| DTO résultat `initiate` | `app/DataTransferObjects/Payments/PublicationPaymentInitiateResult.php` |
| Registre | `app/Services/Payments/PublicationPaymentGatewayRegistry.php` |
| Enregistrement des passerelles | `app/Providers/PaymentServiceProvider.php` |
| Exemple FedaPay | `app/Services/Payments/Gateways/FedaPayPublicationGateway.php` |
| Contrôleur HTTP | `app/Http/Controllers/Api/ListingPublicationPaymentController.php` |
| Vue retour navigateur | `resources/views/payments/return-status.blade.php` |

## Endpoints (préfixe `/api`)

| Méthode | Chemin | Auth | Description |
|---------|--------|------|-------------|
| `GET` | `/listings/{id}/payment/methods` | Sanctum | Frais, devise, liste des moyens avec `available` |
| `POST` | `/listings/{id}/payment/initiate` | Sanctum | Corps JSON : **`gateway`** (obligatoire), `return_to` optionnel (`create` \| `edit`) |
| `GET` | `/listings/{id}/payment/status` | Sanctum | Statut payé / frais |
| `GET` | `/payments/callback/{gateway}` | public | Redirection prestataire → page HTML de statut |

### Breaking change

`POST .../payment/initiate` **exige** le champ `gateway` (identifiant retourné par `.../payment/methods`, ex. `fedapay`).

## Configuration FedaPay

Variables d’environnement (voir `config/fedapay.php`) :

- `FEDAPAY_SECRET_KEY` — clé secrète (obligatoire pour activer la passerelle)
- `FEDAPAY_ENVIRONMENT` — `sandbox` ou `live` (défaut : `sandbox`)
- `FEDAPAY_CURRENCY` — ISO devise (défaut : `XOF`)
- `FEDAPAY_CALLBACK_BASE_URL` — base URL publique joignable par FedaPay (souvent `APP_URL` ou ngrok en dev)

Identifiant de passerelle : `fedapay` (constante `FedaPayPublicationGateway::ID`).

## Ajouter une nouvelle passerelle (ex. Stripe)

1. **Créer une classe** `app/Services/Payments/Gateways/StripePublicationGateway.php` (nom au choix) qui implémente `PublicationPaymentGatewayInterface` :
   - `getId()` : identifiant stable **unique** (ex. `stripe`), utilisé dans `payments.method` et dans l’URL `/payments/callback/{gateway}`.
   - `getLabel()` / `getDescription()` : textes affichés dans l’app.
   - `isConfigured()` : retourner `false` si les clés API manquent (la passerelle apparaîtra comme indisponible).
   - `initiate()` : créer la transaction côté prestataire, enregistrer un `Payment` en attente, retourner `PublicationPaymentInitiateResult` avec l’URL de paiement.
   - `handleCallback()` : valider la requête (signature, etc.), mettre à jour le paiement / annonce, retourner une réponse HTML ou JSON selon le besoin.

2. **Enregistrer la passerelle** dans `PaymentServiceProvider` en l’ajoutant au tableau passé à `PublicationPaymentGatewayRegistry` :

   ```php
   return new PublicationPaymentGatewayRegistry([
       $app->make(FedaPayPublicationGateway::class),
       $app->make(StripePublicationGateway::class),
   ]);
   ```

3. **Configurer** les variables d’environnement et la config nécessaires (fichier `config/services.php` ou fichier dédié).

4. **Exposer le callback** : l’URL est déjà `GET /api/payments/callback/{gateway}` ; le contrôleur résout la passerelle via `getOrFail($gateway)`.

5. **Mobile** : aucune modification obligatoire si le nouvel `id` apparaît dans `GET .../payment/methods` ; l’utilisateur le choisit dans le modal (`PublicationPaymentMethodModal`).

## Flux mobile (résumé)

1. L’utilisateur appuie sur **Payer** → `getListingPublicationPaymentMethods(listingId)`.
2. Si des moyens avec `available: true` existent, ouverture du modal de choix.
3. Au choix : `initiateListingPublicationPayment(listingId, { gateway, return_to })` → navigateur in-app (`WebBrowser`) vers `payment_url`.
4. Puis `getListingPublicationPaymentStatus(listingId)` pour rafraîchir l’état.

Fichiers concernés : `mobile/lib/api.ts`, `mobile/components/PublicationPaymentMethodModal.tsx`, écrans `mobile/app/(owner)/create.tsx` et `edit.tsx`.
