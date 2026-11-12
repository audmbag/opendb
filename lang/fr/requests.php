<?php

return [
    'project_deletion' => [
        'required' => 'Saisissez le nom du projet pour confirmer sa suppression.',
        'in' => 'Le nom saisi ne correspond pas au nom du projet.',
    ],
    'writable_directory' => 'Le chemin doit être un répertoire existant et accessible en écriture.',
    'cloning' => [
        'same_database' => 'La base de données cible ne peut pas être la même que la base de données source.',
        'table_columns' => 'Les colonnes de la table « :table » doivent former un tableau.',
        'column_transformation' => 'La transformation de la colonne « :column » doit être un tableau.',
        'session_expired' => 'La session de clonage est introuvable ou a expiré, relancez la configuration du clonage.',
    ],
    'scalar' => 'Le champ :attribute doit être une valeur simple.',
    'writable_file' => 'Le champ :attribute doit être un chemin de fichier valide et accessible en écriture.',
    'file' => 'Le champ :attribute doit être un chemin de fichier valide.',
    'snippets' => [
        'label' => 'Le nom commence par une lettre, puis ne contient que des lettres, des chiffres, « - » ou « _ » (40 au plus).',
        'distinct' => 'Deux modèles portent déjà ce nom.',
        'empty' => 'Le modèle est vide.',
    ],
    'changes' => [
        'required' => 'Aucune modification à appliquer.',
        'max' => 'Impossible d\'appliquer plus de :max modifications à la fois.',
    ],
    'design' => [
        'distinct' => 'Deux colonnes portent ce nom.',
        'options' => 'Une énumération a besoin d\'au moins une valeur.',
        'default_expression' => 'Saisissez l\'expression SQL de la valeur par défaut.',
        'scale' => 'L\'échelle ne peut dépasser la précision.',
        'columns' => 'Une table a besoin d\'au moins une colonne.',
        'single_auto_increment' => 'Une table ne peut avoir qu\'une colonne auto-incrémentée.',
        'integer_auto_increment' => 'Seule une colonne entière peut être auto-incrémentée.',
        'auto_increment_key' => 'La colonne auto-incrémentée est à elle seule la clé primaire.',
        'referenced_columns' => 'Choisissez autant de colonnes référencées que de colonnes.',
        'type' => 'Choisissez un type pour cette colonne.',
    ],
    'identifier' => 'Un nom commence par une lettre ou « _ », puis ne contient que des lettres, des chiffres, « _ » ou « $ ».',
];
