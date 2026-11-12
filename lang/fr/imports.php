<?php

return [
    'errors' => [
        'unknown_columns' => 'Ces colonnes n\'existent pas dans la table : :columns.',
        'no_mapping' => 'Associez au moins une colonne du fichier à une colonne de la table.',
        'duplicate_mapping' => 'Une colonne de la table ne peut recevoir qu\'une seule colonne du fichier.',
        'rows_refused' => 'Lignes :first à :last du fichier refusées, rien n\'a été importé : :error',
        'unreadable' => 'Le fichier :file ne peut pas être lu : il est vide ou mal compressé.',
        'zip_missing' => 'L\'extension PHP zip n\'est pas disponible pour lire cette archive.',
        'zip_unreadable' => 'L\'archive zip ne peut pas être ouverte.',
        'no_file' => 'L\'archive ne contient aucun fichier .:extension.',
        'empty_script' => 'Le script ne contient aucune instruction SQL.',
    ],
];
