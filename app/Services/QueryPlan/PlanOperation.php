<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

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

    public function label(): string
    {
        return match ($this) {
            self::Result => 'Résultat',
            self::FullScan => 'Parcours complet',
            self::IndexFullScan => "Parcours complet de l'index",
            self::RangeScan => "Parcours d'intervalle",
            self::IndexLookup => 'Recherche par index',
            self::UniqueLookup => 'Recherche unique',
            self::NestedLoop => 'Jointure en boucle',
            self::HashJoin => 'Jointure par hachage',
            self::MergeJoin => 'Jointure par fusion',
            self::Hash => 'Table de hachage',
            self::Group => 'Regroupement',
            self::Sort => 'Tri',
            self::Distinct => 'Dédoublonnage',
            self::Limit => 'Limite',
            self::Subquery => 'Sous-requête',
            self::Materialize => 'Matérialisation',
            self::Union => 'Union',
            self::Other => 'Autre étape',
        };
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
