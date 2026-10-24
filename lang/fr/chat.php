<?php

return [
    'stream_errors' => [
        'interrupted' => 'Le fournisseur d\'IA a interrompu la réponse avant la fin. Veuillez réessayer.',
        'provider' => 'Le fournisseur d\'IA a renvoyé une erreur : « :message »',
        'rate_limited' => 'Le fournisseur d\'IA reçoit trop de requêtes pour le moment. Patientez quelques instants avant de réessayer.',
        'overloaded' => 'Le fournisseur d\'IA est surchargé pour le moment. Veuillez réessayer dans quelques instants.',
        'insufficient_credits' => 'Le compte du fournisseur d\'IA n\'a plus assez de crédits ou de quota pour répondre.',
        'connection' => 'Impossible de joindre le fournisseur d\'IA. Vérifiez la connexion puis réessayez.',
    ],
];
