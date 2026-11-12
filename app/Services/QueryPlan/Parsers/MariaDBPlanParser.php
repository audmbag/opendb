<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Parsers;

use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\PlanOperation;
use App\Support\TranslatableMessage;

/**
 * Le plan JSON de MariaDB : proche de celui de MySQL 5.7, mais le tri et la table temporaire sont des enveloppes
 * (filesort, temporary_table), les tables jointes avec un tampon sont dans block-nl-join et les coûts n'existent
 * qu'à partir de MariaDB 11.
 */
class MariaDBPlanParser extends MySQLPlanParser
{
    protected function driver(): string
    {
        return 'mariadb';
    }

    protected function engine(): string
    {
        return 'MariaDB';
    }

    /**
     * La version de MariaDB, que le serveur peut annoncer derrière un préfixe de compatibilité (« 5.5.5-10.11.6-MariaDB »).
     */
    protected function majorMinor(string $version): string
    {
        return preg_match('/(\d+\.\d+)\.\d+-MariaDB/i', $version, $matches) ? $matches[1] : parent::majorMinor($version);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function totalCost(array $block): ?float
    {
        return $this->float($block['cost'] ?? null);
    }

    /**
     * @param  array<mixed>  $value
     * @return ?list<PlanNode>
     */
    protected function part(string $key, array $value, bool $topLevel): ?array
    {
        return match ($key) {
            'filesort' => [$this->filesort($value, $topLevel)],
            'temporary_table' => [$this->temporaryTable($value, $topLevel)],
            'read_sorted_file', 'expression_cache' => $this->content($value, $topLevel),
            'block-nl-join' => $this->join([['block-nl-join' => $value]]),
            default => parent::part($key, $value, $topLevel),
        };
    }

    /**
     * Le filesort d'une requête avec GROUP BY et sans ORDER BY sert au regroupement : il devient l'étape de regroupement.
     *
     * @param  array<string, mixed>  $value
     */
    protected function filesort(array $value, bool $topLevel): PlanNode
    {
        $temporary = isset($value['temporary_table']);
        $children = $this->content($temporary ? (array) $value['temporary_table'] : $value);
        $sortKey = isset($value['sort_key']) ? (string) $value['sort_key'] : null;

        if ($topLevel && $this->clauses->groupBy() !== null && $this->clauses->orderBy() === null) {
            return $this->groupNode($this->clauses->groupBy(), $children, ['temporary' => $temporary, 'filesort' => true]);
        }

        return $this->sortNode($topLevel ? ($this->clauses->orderBy() ?? $sortKey) : $sortKey, $children, ['filesort' => true, 'temporary' => $temporary]);
    }

    /**
     * Une table temporaire sert au GROUP BY ou au DISTINCT de la requête ; ailleurs, elle stocke un résultat intermédiaire.
     *
     * @param  array<string, mixed>  $value
     */
    protected function temporaryTable(array $value, bool $topLevel): PlanNode
    {
        $children = $this->content($value);

        return match (true) {
            $topLevel && $this->clauses->groupBy() !== null => $this->groupNode($this->clauses->groupBy(), $children, ['temporary' => true]),
            $topLevel && $this->clauses->isDistinct() => new PlanNode(PlanOperation::Distinct, subtitle: 'SELECT DISTINCT', outputLabel: new TranslatableMessage('query_plan.output.distinct_rows'), facts: ['temporary' => true], children: $children),
            default => new PlanNode(PlanOperation::Materialize, subtitle: new TranslatableMessage('query_plan.subtitles.temporary_table'), facts: ['temporary' => true], children: $children),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: ?array<string, mixed>, 1: bool}
     */
    protected function joinedTable(array $item): array
    {
        if (is_array($item['block-nl-join'] ?? null)) {
            $block = $item['block-nl-join'];
            $table = (array) ($block['table'] ?? []);

            if (isset($block['attached_condition'])) {
                $table['attached_condition'] = $block['attached_condition'];
            }

            return [$table === [] ? null : $table, true];
        }

        return parent::joinedTable($item);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isHashJoin(array $item): bool
    {
        return str_contains((string) ($item['block-nl-join']['join_type'] ?? ''), 'BNLH')
            || str_contains((string) ($item['block-nl-join']['table']['access_type'] ?? ''), 'hash');
    }

    /**
     * MariaDB donne le coût propre de chaque table (cost), pas un coût cumulé.
     *
     * @param  array<string, mixed>  $table
     */
    protected function read(array $table, ?int $outerRows = null, float $previousCost = 0.0, bool $inner = false, bool $joinBuffer = false): PlanNode
    {
        $ownCost = $this->float($table['cost'] ?? null);

        if ($ownCost !== null) {
            $table['cost_info'] = ['prefix_cost' => $previousCost + $ownCost];
        }

        if (isset($table['access_type']) && str_starts_with((string) $table['access_type'], 'hash_')) {
            $table['access_type'] = substr((string) $table['access_type'], 5);
        }

        return parent::read($table, $outerRows, $previousCost, $inner, $joinBuffer);
    }
}
