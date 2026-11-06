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
 * Read a statement of the SQL editor to fix the error the database gave, explain it, or make it faster. The agent
 * only writes: the user reads its version, then puts it in the script and runs it.
 */
#[MaxSteps(8)]
class QueryReviewerAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public const array MODES = ['fix', 'explain', 'optimize'];

    /**
     * @param  'fix'|'explain'|'optimize'  $mode
     * @param  ?string  $error  the message of the database, to fix
     * @param  ?array<string, mixed>  $plan  the summary of the execution plan, to optimize
     */
    public function __construct(
        public DriverContract $driver,
        public string $mode,
        public string $sql,
        public ?string $error = null,
        public ?array $plan = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $engine = $this->driver->label();
        $task = match ($this->mode) {
            'fix' => <<<TASK
            La base a refusé cette instruction avec l'erreur suivante :
            {$this->error}

            Trouve la cause et écris l'instruction corrigée. Vérifie les noms de tables et de colonnes avec FocusTable.
            explanation : la cause de l'erreur et ce que tu as changé, en deux ou trois phrases.
            TASK,
            'explain' => <<<'TASK'
            Explique ce que fait cette instruction, étape par étape, pour quelqu'un qui connaît mal le SQL : les tables lues,
            les jointures, les filtres, les regroupements et ce que renvoie le résultat. Préviens si elle modifie ou supprime des données.
            sql : vide, sauf si l'instruction a un défaut évident ; écris alors sa version corrigée.
            TASK,
            'optimize' => <<<TASK
            Rends cette instruction plus rapide sans changer son résultat. Résumé de son plan d'exécution :
            {$this->planSummary()}

            Appuie-toi sur les alertes du plan. Réécris l'instruction si une autre écriture est plus efficace, et propose
            les index utiles (CREATE INDEX …) à la suite, chacun terminé par un point-virgule : ils ne sont pas créés sans l'utilisateur.
            explanation : ce qui coûte dans le plan et ce que changent ta version et tes index.
            sql : vide si l'instruction est déjà efficace.
            TASK,
        };

        return <<<INSTRUCTIONS
        Tu relis une instruction SQL écrite dans l'éditeur SQL de l'utilisateur, pour une base {$engine}. Tu n'exécutes rien.

        Instruction :
        {$this->sql}

        {$task}

        Règles :
        - Écris du SQL valide pour {$engine}, mots-clés, types et fonctions en MAJUSCULES, chaque instruction terminée par un point-virgule.
        - N'invente aucun nom de table ou de colonne : vérifie-les avec FocusTable.
        - sql : les instructions seules, sans bloc Markdown ni commentaire, ou une chaîne vide.
        - explanation : en français, en texte simple, sans Markdown.
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
            'explanation' => $schema->string()->required(),
            // An empty string rather than null: every provider supports a required string in its structured output.
            'sql' => $schema->string()->required(),
        ];
    }

    private function planSummary(): string
    {
        return $this->plan === null
            ? '(indisponible : seule une requête SELECT a un plan)'
            : (string) json_encode($this->plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
