<?php

return [
    'fields' => [
        'id' => 'Identifiant',
        'name' => 'Nom du jeton',
        'project' => 'Projet',
        'token' => 'Jeton',
        'created_at' => 'Créé le',
        'last_used_at' => 'Dernière utilisation',
    ],
    'questions' => [
        'revoke' => 'Révoquer le jeton « :name » ? Les agents qui l\'utilisent perdront l\'accès au projet.',
    ],
    'messages' => [
        'created' => 'Jeton MCP créé sur le projet « :project ».',
        'shown_once' => 'Copiez-le maintenant : il ne sera plus jamais affiché.',
        'client_config' => 'À ajouter à la configuration de votre client MCP :',
        'no_tokens' => 'Aucun jeton MCP.',
        'never_used' => 'Jamais',
        'revoked' => 'Le jeton « :name » est révoqué.',
    ],
    'errors' => [
        'missing_token' => 'Aucun jeton MCP : définissez la variable d\'environnement OPENDB_MCP_TOKEN dans la configuration du client MCP.',
        'unknown_token' => 'Jeton MCP inconnu ou révoqué.',
        'project_out_of_reach' => 'Le projet « :project » n\'est pas accessible avec vos privilèges.',
        'token_not_found' => 'Aucun de vos jetons MCP n\'a l\'identifiant « :token ».',
    ],
];
