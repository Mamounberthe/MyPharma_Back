<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paiement en ligne
    |--------------------------------------------------------------------------
    |
    | Tant qu'aucune intégration réelle (Orange Money / Wave / carte) avec
    | webhook signé n'est en place, le paiement en ligne reste DÉSACTIVÉ.
    | Dans cet état, seul le paiement à la livraison (cash) est proposé.
    |
    | NE PAS activer ce flag tant que initiatePayment() appelle réellement un
    | prestataire et que la confirmation passe par un webhook vérifié — sinon
    | des commandes seraient confirmées sans encaissement réel.
    |
    */

    'online_enabled' => env('PAYMENTS_ONLINE_ENABLED', false),

];
