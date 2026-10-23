<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\ReadOnlyQueryGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExplainQuery extends BaseTool implements Tool
{
    public function __construct(
        public DriverContract $driver,
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
        return $this->artifactOutput(function () use ($request): array {
            $sql = ReadOnlyQueryGuard::assertSelect((string) $request['query']);

            $plan = $this->driver->explain($sql, $request['bindings'] ?? []);

            return [
                'steps' => $this->summarize($plan),
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->selectQuerySchema($schema);
    }

    /**
     * The steps of the plan, one line each, indented by depth, for the agent to explain it.
     *
     * @param  list<array{label: string, detail: ?string, cost: ?float, rows: ?int, warning: ?string, children: list<array<string, mixed>>}>  $nodes
     * @return list<string>
     */
    protected function summarize(array $nodes, int $depth = 0): array
    {
        $steps = [];

        foreach ($nodes as $node) {
            $steps[] = str_repeat('  ', $depth).$node['label']
                .($node['detail'] ? " ({$node['detail']})" : '')
                .($node['rows'] !== null ? " · ~{$node['rows']} lignes" : '')
                .($node['cost'] !== null ? " · coût {$node['cost']}" : '')
                .($node['warning'] ? " ⚠ {$node['warning']}" : '');

            array_push($steps, ...$this->summarize($node['children'], $depth + 1));
        }

        return $steps;
    }
}
