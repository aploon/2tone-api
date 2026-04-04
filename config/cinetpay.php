<?php

return [
    /**
     * Active la passerelle CinetPay pour les frais de publication (liste + initiate).
     * Les clés API doivent correspondre au pays configuré (mêmes identifiants que sur l’app mobile pour l’instant).
     */
    'publication_enabled' => filter_var(env('CINETPAY_PUBLICATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /** Code pays ISO (ex. ML, CI, SN) — utilisé par l’API CinetPay et le webhook. */
    'country' => env('CINETPAY_COUNTRY', 'ML'),

    /** Devise des frais de publication (doit être alignée avec listing.publication_fee). */
    'currency' => env('CINETPAY_CURRENCY', 'XOF'),

    'api_key' => env('CINETPAY_API_KEY'),

    'api_password' => env('CINETPAY_API_PASSWORD'),
];
