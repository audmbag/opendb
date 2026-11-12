<?php

return [
    'operations' => [
        'accounts' => 'la gestion des comptes et des droits',
        'server' => 'la gestion des bases et du serveur',
        'files' => 'l\'accès aux fichiers du serveur',
        'dynamic_sql' => 'l\'exécution de SQL dynamique',
    ],
    'guards' => [
        'administrative' => 'Requête rejetée : :operation est réservée à un administrateur de la base de données et n\'est jamais autorisée, quel que soit le mode. Si la requête vise une colonne portant ce nom, précise le mot-clé COLUMN.',
        'read_only_statements' => 'Requête rejetée : seules les instructions SELECT, WITH, EXPLAIN, SHOW et DESCRIBE sont autorisées par ce tool.',
        'select_only' => 'Requête rejetée : seules les instructions SELECT et WITH sont autorisées par ce tool.',
        'explain_analyze' => 'Requête rejetée : EXPLAIN ANALYZE exécute la requête, seul le plan estimé peut être calculé.',
        'select_into' => 'Requête rejetée : SELECT … INTO écrit le résultat ailleurs et ne peut pas être analysé.',
        'locking' => 'Requête rejetée : une requête qui verrouille des lignes (FOR UPDATE, FOR SHARE) ne peut pas être analysée.',
        'keyword' => 'Requête rejetée : le mot-clé :keyword n\'est pas autorisé dans ce tool en lecture seule.',
        'pragma_assignment' => 'Requête rejetée : les PRAGMA de configuration (avec \'=\') ne sont pas autorisés en lecture seule.',
        'pragma' => 'Requête rejetée : le pragma \':pragma\' n\'est pas dans la liste blanche autorisée.',
        'stacked' => 'Requête rejetée : les requêtes multiples (empilage de statements) ne sont pas autorisées. S\'il n\'y a qu\'une seule instruction, retire les commentaires et passe les valeurs contenant un point-virgule dans `bindings`.',
    ],
    'hidden' => [
        'named' => 'La table [:table] est désactivée pour l\'agent dans les paramètres du projet : tu ne peux ni lire sa structure ou ses données, ni la modifier.',
        'cited' => 'Requête rejetée : elle cite la table [:table], désactivée pour l\'agent dans les paramètres du projet. Un nom de colonne, un alias ou une valeur identique au nom de la table suffit à rejeter la requête.',
        'ask_user' => 'Explique-le à l\'utilisateur et, si sa demande en a besoin, propose-lui de rendre la table accessible dans les paramètres du projet (section « Tables », accès de l\'agent IA). N\'essaie pas de contourner cette restriction.',
        'system_catalog' => 'Requête rejetée : les catalogues système (information_schema, pg_catalog, sqlite_master…) sont inaccessibles tant que des tables sont désactivées pour l\'agent, car ils décrivent aussi ces tables. Utilise les tools `ListTables` et `FocusTable` pour explorer la structure de la base.',
        'dynamic_sql' => 'Requête rejetée : le SQL dynamique (PREPARE, EXECUTE, fonctions qui exécutent une requête passée en texte…) est interdit tant que des tables sont désactivées pour l\'agent, car il permettrait de les atteindre sans les nommer. Écris la requête directement.',
    ],
    'errors' => [
        'no_driver' => 'Aucun driver disponible pour [:driver].',
        'directory_not_writable' => 'le dossier [:directory] n\'est pas accessible en écriture',
        'cannot_create' => 'La base de données [:database] n\'existe pas et l\'utilisateur n\'a pas le droit de la créer.',
        'cannot_create_because' => 'La base de données [:database] n\'existe pas et l\'utilisateur n\'a pas le droit de la créer (:reason).',
        'creation_failed' => 'La création de la base de données [:database] a échoué.',
        'unsupported' => 'L\'opération [:operation] n\'est pas supportée par le driver [:driver].',
        'table_not_found' => 'La table [:table] n\'existe pas dans la base de données ou n\'est pas accessible. Utilise le tool `ListTables` pour vérifier les noms exacts des tables disponibles.',
        'sort_column' => 'La colonne de tri [:column] n\'existe pas dans le résultat de la requête.',
        'focused_table_missing' => 'La table spécifiée dans `focusedTable.tableName` n\'existe pas dans la base de données. Utilise le tool `ListTables` pour vérifier les noms exacts des tables disponibles.',
    ],
    'records' => [
        'invalid_key' => 'La clé doit être un objet `colonne => valeur` portant sur des colonnes de la table [:table] : :columns.',
        'not_found' => 'Aucun enregistrement de la table [:table] ne correspond à la clé :key.',
    ],
];
