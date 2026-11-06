<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Data;

use App\Services\QueryPlan\PlanOperation;

/**
 * Un plan d'exécution normalisé : une liste plate d'étapes dont la racine est toujours le résultat de la requête.
 */
final class Plan
{
    /**
     * Les étapes dans l'ordre de l'arbre, la racine en premier.
     *
     * @var list<PlanNode>
     */
    public array $nodes = [];

    /**
     * @var array<string, PlanNode>
     */
    private array $parents = [];

    /**
     * @param  string  $driver  Le driver Laravel (mysql, mariadb, pgsql, sqlite).
     * @param  string  $engine  Le nom du moteur dans les phrases (« MySQL lit les lignes… »).
     * @param  array{columns: ?list<string>, rows: ?list<list<mixed>>, text: ?string}  $classic
     */
    public function __construct(
        public readonly string $driver,
        public readonly string $driverLabel,
        public readonly string $engine,
        public readonly bool $hasCosts,
        PlanNode $root,
        public readonly array $classic,
        public readonly mixed $raw,
    ) {
        $this->flatten($root, null);

        $total = $this->totalCost();

        foreach ($this->nodes as $node) {
            $node->costShare = $this->hasCosts && $total > 0 && $node->selfCost !== null && $node->operation !== PlanOperation::Result
                ? round($node->selfCost / $total, 3)
                : null;
        }
    }

    public function root(): PlanNode
    {
        return $this->nodes[0];
    }

    public function parent(PlanNode $node): ?PlanNode
    {
        return $this->parents[$node->id] ?? null;
    }

    /**
     * Toutes les étapes sous celle-ci, dans l'ordre de l'arbre.
     *
     * @return list<PlanNode>
     */
    public function descendants(PlanNode $node): array
    {
        return array_merge(...array_map(fn (PlanNode $child): array => [$child, ...$this->descendants($child)], $node->children));
    }

    /**
     * Les étapes dans l'ordre d'exécution : chaque étape après celles qui l'alimentent.
     *
     * @return list<PlanNode>
     */
    public function executionOrder(?PlanNode $node = null): array
    {
        $node ??= $this->root();

        return [...array_merge(...array_map(fn (PlanNode $child): array => $this->executionOrder($child), $node->children)), $node];
    }

    /**
     * Le nombre de lignes que l'étape reçoit de celles qui l'alimentent, quand toutes l'estiment.
     */
    public function inputRows(PlanNode $node): ?int
    {
        if ($node->children === [] || array_any($node->children, fn (PlanNode $child): bool => $child->estimatedRows === null)) {
            return null;
        }

        return array_sum(array_map(fn (PlanNode $child): int => (int) $child->estimatedRows, $node->children));
    }

    public function totalCost(): ?float
    {
        return $this->hasCosts ? $this->root()->cost : null;
    }

    /**
     * La somme des lignes examinées par les lectures de tables.
     */
    public function rowsRead(): int
    {
        return array_sum(array_map(fn (PlanNode $node): int => $node->operation->isRead() ? (int) $node->rowsExamined : 0, $this->nodes));
    }

    /**
     * Les tables réelles lues par le plan, sans les tables dérivées.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return $this->nodes
            |> (fn (array $nodes): array => array_filter($nodes, fn (PlanNode $node): bool => $node->table !== null && ! str_starts_with($node->table, '<')))
            |> (fn (array $nodes): array => array_map(fn (PlanNode $node): string => (string) $node->table, $nodes))
            |> array_unique(...)
            |> array_values(...);
    }

    /**
     * @return list<array{code: string, severity: string, message: string, table: ?string, columns: list<string>, status?: string}>
     */
    public function alerts(): array
    {
        return array_merge(...array_map(fn (PlanNode $node): array => $node->alerts, $this->nodes));
    }

    /**
     * @return array{cost: ?float, rowsRead: int, alerts: array{danger: int, warn: int}}
     */
    public function totals(): array
    {
        $severities = array_column($this->alerts(), 'severity');

        return [
            'cost' => $this->totalCost(),
            'rowsRead' => $this->rowsRead(),
            'alerts' => [
                'danger' => count(array_keys($severities, 'danger', true)),
                'warn' => count(array_keys($severities, 'warn', true)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'driverLabel' => $this->driverLabel,
            'hasCosts' => $this->hasCosts,
            'totals' => $this->totals(),
            'nodes' => array_map(fn (PlanNode $node): array => $node->toArray(), $this->nodes),
            'classic' => $this->classic,
            'raw' => $this->raw,
        ];
    }

    private function flatten(PlanNode $node, ?PlanNode $parent): void
    {
        $node->id = 'n'.(count($this->nodes) + 1);
        $node->parentId = $parent?->id;
        $this->nodes[] = $node;

        if ($parent !== null) {
            $this->parents[$node->id] = $parent;
        }

        foreach ($node->children as $child) {
            $this->flatten($child, $node);
        }
    }
}
