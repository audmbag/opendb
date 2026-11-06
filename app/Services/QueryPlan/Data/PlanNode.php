<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Data;

use App\Services\QueryPlan\PlanOperation;

/**
 * Une étape du plan d'exécution normalisé. Les parsers la remplissent avec ce que le moteur dit de l'étape ;
 * les alertes, l'explication, les détails et la comparaison sont ajoutés ensuite sur le plan complet.
 */
final class PlanNode
{
    public string $id = '';

    public ?string $parentId = null;

    public string $label;

    public ?float $costShare = null;

    /**
     * @var list<array{code: string, severity: string, message: string, table: ?string, columns: list<string>, status?: string}>
     */
    public array $alerts = [];

    public string $explanation = '';

    /**
     * @var list<array{label: string, value: string, mono?: bool, tone?: string}>
     */
    public array $details = [];

    /**
     * @var ?array{label: string, rowsExamined: ?int, estimatedRows: ?int, inputRows: ?int, cost: ?float, selfCost: ?float}
     */
    public ?array $before = null;

    /**
     * @param  ?array{label: string, value: string}  $condition
     * @param  array{accessType?: ?string, possibleKeys?: ?list<string>, filter?: ?string, exactFilter?: bool, joinCondition?: ?string, joinBuffer?: bool, key?: ?string, temporary?: bool, filesort?: bool, covering?: bool, dependent?: bool, limit?: ?int}  $facts  Ce que le moteur dit de l'étape, pour les alertes et les explications.
     * @param  list<PlanNode>  $children  Les étapes qui alimentent celle-ci, dans l'ordre d'exécution.
     */
    public function __construct(
        public PlanOperation $operation,
        ?string $label = null,
        public ?string $subtitle = null,
        public ?string $table = null,
        public ?string $index = null,
        public ?string $badge = null,
        public ?int $rowsExamined = null,
        public ?int $estimatedRows = null,
        public ?string $outputLabel = null,
        public ?float $cost = null,
        public ?float $selfCost = null,
        public ?array $condition = null,
        public array $facts = [],
        public array $children = [],
    ) {
        $this->label = $label ?? $operation->label();
    }

    public function hasAlert(string $severity): bool
    {
        return array_any($this->alerts, fn (array $alert): bool => $alert['severity'] === $severity);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parentId' => $this->parentId,
            'operation' => $this->operation->value,
            'label' => $this->label,
            'subtitle' => $this->subtitle,
            'table' => $this->table,
            'index' => $this->index,
            'badge' => $this->badge,
            'rowsExamined' => $this->rowsExamined,
            'estimatedRows' => $this->estimatedRows,
            'outputLabel' => $this->outputLabel,
            'cost' => $this->cost,
            'selfCost' => $this->selfCost,
            'costShare' => $this->costShare,
            'condition' => $this->condition,
            'explanation' => $this->explanation,
            'alerts' => $this->alerts,
            'details' => $this->details,
            'before' => $this->before,
        ];
    }
}
