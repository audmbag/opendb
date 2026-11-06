<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Services\QueryPlan\Exception\QueryPlanException;
use App\Services\QueryPlan\QueryPlanManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExplainQuery extends BaseTool implements Tool
{
    /**
     * @param  ?string  $conversationId  La conversation de l'appel, pour comparer le plan au précédent plan de la même requête.
     */
    public function __construct(
        public DriverContract $driver,
        public QueryPlanManager $queryPlans,
        public string $projectId,
        public ?string $conversationId = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Calcule le plan d\'exécution d\'une requête SELECT (sans l\'exécuter) et l\'affiche dans l\'interface '
            .'sous forme d\'arbre, en signalant les étapes coûteuses (parcours complet de table, tri temporaire). '
            .'Ce tool te renvoie aussi un résumé des étapes et des alertes : appuie-toi dessus pour expliquer le plan '
            .'et proposer des index concrets (CREATE INDEX …) sans les créer toi-même. Utilise-le quand l\'utilisateur '
            .'demande pourquoi une requête est lente, son plan d\'exécution ou comment l\'optimiser. '
            .'N\'écris pas toi-même le mot-clé EXPLAIN : passe la requête SELECT telle quelle.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $plan = $this->queryPlans->explain($this->driver, $this->projectId, $this->conversationId, (string) $request['query'], (array) ($request['bindings'] ?? []));
        } catch (QueryPlanException $e) {
            return $this->toJson($e->toArray());
        }

        return $this->toJson($this->queryPlans->summarize($plan));
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->selectQuerySchema($schema);
    }
}
