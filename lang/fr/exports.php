<?php

return [
    'formats' => [
        'sql' => 'SQL (script)',
        'csv' => 'CSV (un fichier par table)',
    ],
    'compressions' => [
        'none' => 'Aucune',
        'zip' => 'Zip (.zip)',
        'gzip' => 'Gzip (.gz)',
        'tar' => 'Tar (.tar)',
    ],
    'short_formats' => [
        'sql' => 'Dump SQL',
        'csv' => 'CSV',
    ],
    'all_tables' => 'Toutes les tables',
    'errors' => [
        'empty_query' => 'Rien à exporter : la requête ne renvoie aucune ligne.',
        'zip_missing' => 'L\'extension PHP zip n\'est pas disponible.',
        'archive_create' => 'Impossible de créer l\'archive :path.',
        'archive_close' => 'Impossible de finaliser l\'archive :path.',
        'archive_create_because' => 'Impossible de créer l\'archive :path : :error',
        'write' => 'Impossible d\'écrire le fichier :path.',
        'unknown_compression' => 'Format de compression inconnu : :compression. Formats possibles : :formats.',
        'unsupported' => 'L\'export n\'est pas disponible pour les bases :driver.',
        'no_dialect' => 'Aucun dialecte SQL d\'export pour les bases :driver.',
        'unknown_tables' => 'Ces tables n\'existent pas dans la base de données : :tables. Utilise le tool `ListTables` pour vérifier les noms exacts des tables disponibles.',
        'nothing_selected' => 'Rien à exporter : chaque table demandée exclut à la fois son schéma et ses données.',
    ],
];
