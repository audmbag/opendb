<?php

namespace App\Ai\Agents\Tables;

use App\Ai\Tools\Database\DatabaseSchema;
use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ReadQuery;
use App\Database\Contracts\DriverContract;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Answer a quick question about the table shown in the Tables tab. The exchange is not saved as a conversation,
 * and the agent only reads the database.
 */
#[MaxSteps(8)]
class TableChatAgent implements Agent, HasTools
{
    use Promptable;

    /**
     * @param  array<string, mixed>  $definition  the columns, keys and indexes of the table
     * @param  string  $view  the statement reading the rows the user is looking at, with its filters and sort
     */
    public function __construct(
        public DriverContract $driver,
        public string $table,
        public array $definition,
        public string $view,
    ) {}

    public function instructions(): Stringable|string
    {
        $definition = json_encode($this->definition, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return <<<INSTRUCTIONS
        Tu es l'assistant SQL d'une base {$this->driver->label()}. L'utilisateur regarde la table « {$this->table} » dans l'explorateur de tables
        et te pose une question rapide à son sujet, dans un petit panneau à côté des données.

        Structure de la table :
        {$definition}

        Requête des lignes qu'il a sous les yeux, avec ses filtres et son tri :
        {$this->view}

        Règles :
        - Réponds en français, brièvement et directement : quelques phrases, une petite liste ou un petit tableau Markdown.
        - « Ces lignes », « les lignes affichées » ou « ma sélection » désignent le résultat de la requête ci-dessus.
        - Pour répondre avec des chiffres, exécute une requête en lecture avec l'outil prévu plutôt que de les deviner. Limite toujours les lignes lues.
        - Tu ne peux rien modifier dans la base. Si l'utilisateur demande une modification, donne-lui la requête à exécuter dans un bloc ```sql
          et précise qu'il peut la lancer depuis la console SQL ou le chat.
        - Montre la requête SQL utilisée quand elle aide à comprendre la réponse.
        - Écris toujours les mots-clés, les types et les fonctions SQL en MAJUSCULES (SELECT, FROM, WHERE, COUNT…).
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new ReadQuery($this->driver, maxRows: 100),
            new ProfileTable($this->driver),
            new FocusTable($this->driver),
            new DatabaseSchema($this->driver),
        ];
    }
}
