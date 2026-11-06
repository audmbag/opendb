<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Services\QueryPlan\Data\Plan;
use App\Services\QueryPlan\Data\PlanNode;

/**
 * Les alertes d'un plan normalisé, avec leurs codes fixes, la table et les colonnes concernées : c'est ce qui permet
 * à l'agent de proposer un index juste.
 */
final class PlanAlertDetector
{
    /**
     * Au-delà, une lecture par index examine tout de même trop de lignes.
     */
    public const int LARGE_ESTIMATE_ROWS = 100_000;

    /**
     * @param  array<string, array{columns: list<string>, indexes: list<array<string, mixed>>}>  $tables
     */
    public function detect(Plan $plan, SqlClauses $clauses, array $tables): void
    {
        foreach ($plan->nodes as $node) {
            $node->table ??= $this->keyTable($plan, $node, $clauses, $tables);
        }

        foreach ($plan->nodes as $node) {
            $node->alerts = $this->alerts($plan, $node, $clauses, $tables);
        }
    }

    /**
     * @param  array<string, array{columns: list<string>, indexes: list<array<string, mixed>>}>  $tables
     * @return list<array{code: string, severity: string, message: string, table: ?string, columns: list<string>}>
     */
    private function alerts(Plan $plan, PlanNode $node, SqlClauses $clauses, array $tables): array
    {
        $alerts = [];
        $key = $node->facts['key'] ?? null;

        if ($node->operation === PlanOperation::FullScan) {
            $parent = $plan->parent($node);

            if (($node->facts['joinBuffer'] ?? false) || ($parent?->operation === PlanOperation::NestedLoop && $parent->children[0] !== $node)) {
                $alerts[] = $this->alert('JOIN_WITHOUT_INDEX', 'danger', 'Jointure sans index', $node->table, $this->columns((string) ($node->facts['joinCondition'] ?? ''), $node, $clauses, $tables));
            }

            $filter = $node->facts['filter'] ?? null;

            if ($filter !== null) {
                $columns = $this->columns($filter, $node, $clauses, $tables);

                if ($columns !== [] || ($node->facts['exactFilter'] ?? false)) {
                    $alerts[] = $this->alert('FULL_SCAN', 'danger', 'Parcours complet de la table', $node->table, $columns);
                } else {
                    $node->facts['filter'] = null;
                    $node->condition = null;
                }
            }
        }

        if ($node->operation->isIndexRead() && $node->rowsExamined !== null && $node->rowsExamined > self::LARGE_ESTIMATE_ROWS) {
            $alerts[] = $this->alert('LARGE_ESTIMATE', 'warn', 'Beaucoup de lignes lues par l\'index', $node->table, []);
        }

        if (in_array($node->operation, [PlanOperation::Group, PlanOperation::Distinct], true) && ($node->facts['temporary'] ?? false)) {
            $alerts[] = $this->alert('TEMP_TABLE', 'warn', 'Table temporaire', $node->table, $this->columns((string) $key, $node, $clauses, $tables));
        }

        if (in_array($node->operation, [PlanOperation::Sort, PlanOperation::Group], true) && ($node->facts['filesort'] ?? false)) {
            $alerts[] = $this->alert(in_array($plan->driver, ['mysql', 'mariadb'], true) ? 'FILESORT' : 'SORT', 'warn', 'Tri sans index', $node->table, $this->columns((string) $key, $node, $clauses, $tables));
        }

        if ($node->operation === PlanOperation::Subquery && ($node->facts['dependent'] ?? false)) {
            $alerts[] = $this->alert('DEPENDENT_SUBQUERY', 'warn', 'Sous-requête recalculée pour chaque ligne', $node->table ?? $this->firstReadTable($plan, $node), []);
        }

        return $alerts;
    }

    /**
     * @param  list<string>  $columns
     * @return array{code: string, severity: string, message: string, table: ?string, columns: list<string>}
     */
    private function alert(string $code, string $severity, string $message, ?string $table, array $columns): array
    {
        return ['code' => $code, 'severity' => $severity, 'message' => $message, 'table' => $table, 'columns' => $columns];
    }

    /**
     * Les colonnes de la table de l'étape citées dans une expression : sans préfixe, ou avec le préfixe de cette table.
     *
     * @param  array<string, array{columns: list<string>, indexes: list<array<string, mixed>>}>  $tables
     * @return list<string>
     */
    private function columns(string $expression, PlanNode $node, SqlClauses $clauses, array $tables): array
    {
        $table = $node->table;
        $known = array_map(mb_strtolower(...), $tables[$table ?? '']['columns'] ?? []);
        $alias = mb_strtolower((string) ($node->facts['alias'] ?? $table));

        $columns = [];

        foreach (SqlClauses::columnReferences($expression) as $reference) {
            $qualifier = $reference['table'];

            if ($qualifier !== null && mb_strtolower($qualifier) !== $alias && $clauses->tableFor($qualifier) !== $table && $qualifier !== $table) {
                continue;
            }

            if ($known !== [] && ! in_array(mb_strtolower($reference['column']), $known, true)) {
                continue;
            }

            $columns[] = $reference['column'];
        }

        return array_values(array_unique($columns));
    }

    /**
     * La table d'un regroupement ou d'un tri : celle que désigne le préfixe de la clé, sinon la table lue en dessous
     * qui contient les colonnes de la clé.
     *
     * @param  array<string, array{columns: list<string>, indexes: list<array<string, mixed>>}>  $tables
     */
    private function keyTable(Plan $plan, PlanNode $node, SqlClauses $clauses, array $tables): ?string
    {
        if (! in_array($node->operation, [PlanOperation::Group, PlanOperation::Sort, PlanOperation::Distinct, PlanOperation::Hash], true)) {
            return null;
        }

        $references = SqlClauses::columnReferences((string) ($node->facts['key'] ?? ''));
        $qualifier = array_first(array_filter(array_column($references, 'table')));

        if ($qualifier !== null && $clauses->tableFor($qualifier) !== null) {
            return $clauses->tableFor($qualifier);
        }

        $readTables = array_values(array_unique(array_filter(array_map(
            fn (PlanNode $descendant): ?string => $descendant->operation->isRead() ? $descendant->table : null,
            $plan->descendants($node),
        ))));

        if (count($readTables) <= 1) {
            return $readTables[0] ?? null;
        }

        $columns = array_map(fn (array $reference): string => mb_strtolower($reference['column']), $references);

        return array_find($readTables, fn (string $table): bool => $columns !== [] && array_diff($columns, array_map(mb_strtolower(...), $tables[$table]['columns'] ?? [])) === []);
    }

    private function firstReadTable(Plan $plan, PlanNode $node): ?string
    {
        return array_find($plan->descendants($node), fn (PlanNode $descendant): bool => $descendant->operation->isRead())?->table;
    }
}
