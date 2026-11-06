<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Parsers;

use App\Services\QueryPlan\Data\PlanNode;
use App\Services\QueryPlan\PlanOperation;
use App\Services\QueryPlan\SqlClauses;

/**
 * Ce que les parsers de chaque moteur ont en commun : la racine du plan et la lecture des nombres.
 */
abstract class BasePlanParser implements QueryPlanParser
{
    protected SqlClauses $clauses;

    /**
     * La racine ajoutée à chaque plan : le résultat de la requête, qui porte son coût total.
     *
     * @param  list<PlanNode>  $children
     */
    protected function resultNode(?float $cost, array $children): PlanNode
    {
        $columns = $this->clauses->selectList();

        return new PlanNode(
            operation: PlanOperation::Result,
            subtitle: $columns === null ? null : 'SELECT '.$columns,
            cost: $cost,
            selfCost: $cost === null ? null : 0.0,
            condition: $columns === null ? null : ['label' => 'Colonnes renvoyées', 'value' => $columns],
            children: $children,
        );
    }

    /**
     * La version majeure et mineure d'une chaîne de version (« 8.0.36-0ubuntu » → « 8.0 »).
     */
    protected function majorMinor(string $version): string
    {
        return preg_match('/(\d+)\.(\d+)/', $version, $matches) ? "{$matches[1]}.{$matches[2]}" : $version;
    }

    protected function float(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    protected function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    /**
     * Le coût propre d'une étape : son coût cumulé moins celui des étapes qui l'alimentent.
     *
     * @param  list<PlanNode>  $children
     */
    protected function selfCost(?float $cost, array $children): ?float
    {
        if ($cost === null) {
            return null;
        }

        return round(max(0.0, $cost - array_sum(array_map(fn (PlanNode $child): float => (float) $child->cost, $children))), 2);
    }

    /**
     * Le badge d'une lecture : couvrante, ou complète faute d'index.
     *
     * @param  ?list<string>  $possibleKeys
     */
    protected function readBadge(PlanOperation $operation, bool $covering, ?array $possibleKeys = null): ?string
    {
        return match (true) {
            $covering => 'index couvrant',
            $operation === PlanOperation::FullScan && $possibleKeys !== null && $possibleKeys !== [] => 'aucun index utilisable',
            $operation === PlanOperation::FullScan => 'aucun index',
            default => null,
        };
    }

    /**
     * La condition affichée pour une clé de tri ou de regroupement.
     *
     * @return ?array{label: string, value: string}
     */
    protected function keyCondition(string $label, ?string $key): ?array
    {
        return $key === null || $key === '' ? null : ['label' => $label, 'value' => $key];
    }
}
