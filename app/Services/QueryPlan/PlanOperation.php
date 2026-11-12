<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Support\TranslatableMessage;

/**
 * Les étapes d'un plan d'exécution normalisé, communes à tous les moteurs.
 */
enum PlanOperation: string
{
    case Result = 'result';
    case FullScan = 'full_scan';
    case IndexFullScan = 'index_full_scan';
    case RangeScan = 'range_scan';
    case IndexLookup = 'index_lookup';
    case UniqueLookup = 'unique_lookup';
    case NestedLoop = 'nested_loop';
    case HashJoin = 'hash_join';
    case MergeJoin = 'merge_join';
    case Hash = 'hash';
    case Group = 'group';
    case Sort = 'sort';
    case Distinct = 'distinct';
    case Limit = 'limit';
    case Subquery = 'subquery';
    case Materialize = 'materialize';
    case Union = 'union';
    case Other = 'other';

    public function label(): TranslatableMessage
    {
        return new TranslatableMessage("query_plan.operations.{$this->value}");
    }

    /**
     * Une lecture des lignes d'une table, directement ou par un index.
     */
    public function isRead(): bool
    {
        return in_array($this, [self::FullScan, self::IndexFullScan, self::RangeScan, self::IndexLookup, self::UniqueLookup], true);
    }

    public function isIndexRead(): bool
    {
        return $this->isRead() && $this !== self::FullScan;
    }

    public function isJoin(): bool
    {
        return in_array($this, [self::NestedLoop, self::HashJoin, self::MergeJoin], true);
    }
}
