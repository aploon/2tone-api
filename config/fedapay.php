<?php

return [
    'secret_key' => env('FEDAPAY_SECRET_KEY'),
    'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
    'currency_iso' => env('FEDAPAY_CURRENCY', 'XOF'),
    /**
     * URL de base pour le callback HTTP (sans /api/...). Par défaut APP_URL.
     * Doit être joignable publiquement par FedaPay (ex. ngrok en dev).
     */
    'callback_base_url' => env('FEDAPAY_CALLBACK_BASE_URL', env('APP_URL', 'http://localhost')),
];
