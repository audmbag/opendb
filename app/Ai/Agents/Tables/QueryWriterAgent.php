<?php

namespace App\Ai\Agents\Tables;

use App\Ai\Tools\Database\FocusTable;
use App\Ai\Tools\Database\ListTables;
use App\Database\Contracts\DriverContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Write the SQL the user describes in the console. The statements are only written: the user reads them, then runs them.
 */
#[MaxSteps(8)]
class QueryWriterAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * @param  list<string>  $tables  the names of the tables the agent may read
     * @param  string  $script  what the console already holds, to build on it when the user refers to it
     */
    public function __construct(
        public DriverContract $driver,
        public array $tables,
        public string $script,
    ) {}

    public function instructions(): Stringable|string
    {
        $tables = implode(', ', $this->tables);
        $script = $this->script === '' ? '(vide)' : $this->script;

        return <<<INSTRUCTIONS
        Tu écris des requêtes SQL pour une base {$this->driver->label()}, dans la console SQL de l'utilisateur. Il décrit ce qu'il veut en français,
        tu écris les instructions, il les relit puis les exécute lui-même. Tu n'exécutes rien.

        Tables de la base : {$tables}

        Script déjà présent dans la console :
        {$script}

        Règles :
        - Vérifie les colonnes des tables avec l'outil FocusTable avant de les utiliser : n'invente aucun nom.
        - Écris du SQL valide pour {$this->driver->label()}, mots-clés, types et fonctions en MAJUSCULES, chaque instruction terminée par un point-virgule.
        - Mets les noms de tables et de colonnes entre les guillemets du moteur quand ils sont des mots réservés ou contiennent des caractères spéciaux.
        - Limite toujours une lecture qui peut renvoyer beaucoup de lignes (LIMIT 100 par défaut).
        - Si la demande s'appuie sur le script déjà présent (« ajoute un filtre », « la même pour… »), écris la nouvelle version complète.
        - sql : les instructions seules, sans bloc Markdown ni commentaire.
        - explanation : une phrase en français qui dit ce que font les instructions, et prévient si elles modifient ou suppriment des données.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new ListTables($this->driver),
            new FocusTable($this->driver),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()->required(),
            'explanation' => $schema->string()->required(),
        ];
    }
}
