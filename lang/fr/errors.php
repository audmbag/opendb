<?php

return [
    'code' => 'Erreur :status',
    'back' => 'Retour',
    'home' => 'Accueil',
    'reload' => 'Recharger',
    'another_excuse' => 'Une autre excuse',
    'mascot' => 'La base de données d\'opendb, un peu perdue elle aussi',
    'pages' => [
        'forbidden' => [
            'title' => 'Accès refusé',
            'description' => 'Vos privilèges ne vous ouvrent pas cette page. Un administrateur peut vous les accorder.',
            'result' => 'ERREUR 1044 (42000) : accès refusé pour l\'utilisateur courant',
            'excuses' => [
                'bouncer' => 'Le videur a vérifié la liste deux fois. Vous n\'y êtes pas.',
                'grant' => 'Un GRANT bien placé et cette page est à vous.',
                'root' => 'Même root a dû demander, au début.',
                'velvet' => 'Cette table est derrière une corde de velours.',
            ],
        ],
        'not_found' => [
            'title' => 'Page introuvable',
            'description' => 'Cette adresse ne mène nulle part : la page a été déplacée, supprimée, ou n\'a jamais existé.',
            'result' => 'Ensemble vide (0,00 s)',
            'excuses' => [
                'dropped' => 'Quelqu\'un a lancé un DROP sans WHERE. Personne n\'avoue.',
                'join' => 'Même un FULL OUTER JOIN ne l\'a pas retrouvée.',
                'index' => 'Elle n\'était pas indexée. On a fouillé toute la table, ligne par ligne.',
                'null' => 'Elle vaut NULL, et NULL n\'est égal à rien, pas même à lui-même.',
                'vacation' => 'Cette page est partie en vacances sans laisser de clé étrangère.',
            ],
        ],
        'page_expired' => [
            'title' => 'Session expirée',
            'description' => 'La page est restée ouverte trop longtemps et sa session a expiré. Rechargez-la pour reprendre.',
            'result' => 'ERREUR 40001 : session expirée, transaction annulée (ROLLBACK)',
            'excuses' => [
                'coffee' => 'La pause café a duré plus longtemps que la transaction.',
                'timeout' => 'Le jeton a attendu, attendu… puis il est parti.',
                'savepoint' => 'Pas de SAVEPOINT cette fois. Un rechargement et on repart.',
            ],
        ],
        'too_many_requests' => [
            'title' => 'Trop de requêtes',
            'description' => 'Vous allez plus vite que le serveur. Soufflez quelques secondes puis réessayez.',
            'result' => 'ERREUR 1040 (08004) : trop de connexions',
            'excuses' => [
                'breath' => 'Le pool de connexions reprend son souffle.',
                'queue' => 'Vos requêtes font la queue jusque dans le couloir.',
                'limit' => 'LIMIT existe aussi pour les humains.',
            ],
        ],
        'server_error' => [
            'title' => 'Erreur du serveur',
            'description' => 'Quelque chose s\'est mal passé de notre côté. L\'erreur est consignée dans les journaux d\'opendb.',
            'result' => 'ERREUR 40P01 : interblocage détecté',
            'excuses' => [
                'deadlock' => 'Deux processus se tiennent la porte. Ça peut durer.',
                'semicolon' => 'Il manquait un point-virgule. Quelque part. Sûrement.',
                'friday' => 'Quelqu\'un a déployé un vendredi.',
                'cosmic' => 'Un rayon cosmique a inversé un bit. C\'est notre version et on s\'y tient.',
            ],
        ],
        'service_unavailable' => [
            'title' => 'Maintenance en cours',
            'description' => 'opendb fait un peu de ménage et revient dans un instant.',
            'result' => 'Requête mise en attente : la base fait la sieste',
            'excuses' => [
                'vacuum' => 'On passe l\'aspirateur dans les tables. Les miettes de lignes mortes partent.',
                'reindex' => 'On range les index par ordre alphabétique. Ça prend un moment.',
                'nap' => 'Même une base de données a besoin de dormir.',
            ],
        ],
    ],
];
