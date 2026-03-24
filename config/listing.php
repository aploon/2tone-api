<?php

return [
    /** Montant (FCFA) facturé pour la publication d’une annonce — utilisé lors de l’enregistrement du paiement simulé. */
    'publication_fee' => (int) env('LISTING_PUBLICATION_FEE', 5000),
];
