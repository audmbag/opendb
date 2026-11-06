<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Services\QueryPlan\Data\Plan;
use App\Services\QueryPlan\Data\PlanNode;

/**
 * Les phrases d'explication et les lignes du panneau de détail de chaque étape, écrites à partir de modèles
 * pour rester stables et exactes. En comparaison, elles citent aussi les chiffres d'avant.
 */
final class PlanExplainer
{
    /**
     * @param  array<string, mixed>  $bindings
     */
    public function explain(Plan $plan, array $bindings): void
    {
        foreach ($plan->nodes as $node) {
            $node->explanation = $this->explanation($plan, $node);
            $node->details = $this->details($plan, $node, $bindings);
        }
    }

    private function explanation(Plan $plan, PlanNode $node): string
    {
        $engine = $plan->engine;
        $covering = ($node->facts['covering'] ?? false) ? " L'index contient toutes les colonnes nécessaires : la table elle-même n'est jamais lue." : '';
        $key = $node->facts['key'] ?? null;
        $inputRows = $plan->inputRows($node) ?? $node->estimatedRows;
        $parent = $plan->parent($node);

        $sentence = match ($node->operation) {
            PlanOperation::Result => $plan->totals()['alerts']['danger'] + $plan->totals()['alerts']['warn'] > 0
                ? 'Les lignes produites par les étapes ci-dessous sont renvoyées. Les étapes signalées sont celles à améliorer en priorité.'
                : 'Les lignes produites par les étapes ci-dessous sont renvoyées. Aucune étape coûteuse n\'a été détectée.',
            PlanOperation::FullScan => match (true) {
                $this->hasAlert($node, 'JOIN_WITHOUT_INDEX') => "Pour chaque ligne de l'étape précédente, {$engine} relit toute la table {$node->table} : aucun index ne permet d'aller directement aux lignes qui correspondent à la jointure.",
                $this->hasAlert($node, 'FULL_SCAN') => "{$engine} lit ".($node->rowsExamined === null ? 'toutes les' : 'les '.PlanFormat::rows($node->rowsExamined)).' lignes de la table une par une, puis garde celles qui respectent le filtre. Aucun index ne permet d\'aller directement aux lignes utiles.',
                $parent?->operation === PlanOperation::Hash => 'Lecture complète normale : toute la table sert à construire la table de hachage. Aucun filtre n\'est appliqué, donc rien n\'est lu pour rien.',
                default => 'Lecture complète normale : toute la table est nécessaire à l\'étape suivante.',
            },
            PlanOperation::IndexFullScan => "{$engine} parcourt tout l'index ".($node->index ?? '').' au lieu de la table : c\'est plus léger qu\'un parcours complet, mais toutes ses entrées sont lues.'.$covering,
            PlanOperation::RangeScan => "{$engine} descend dans l'index ".($node->index ?? '')." jusqu'au début de l'intervalle et ne lit que les lignes utiles.".$covering,
            PlanOperation::IndexLookup => "{$engine} utilise l'index ".($node->index ?? '').' pour aller directement aux lignes qui ont la valeur cherchée.'.$covering,
            PlanOperation::UniqueLookup => "{$engine} trouve au plus une ligne grâce à l'index unique ".($node->index ?? '').'.',
            PlanOperation::NestedLoop => "Pour chaque ligne de la première entrée, {$engine} cherche les lignes correspondantes dans la seconde.",
            PlanOperation::HashJoin => "{$engine} construit une table de hachage avec une des entrées, puis y associe chaque ligne de l'autre.",
            PlanOperation::MergeJoin => 'Les deux entrées, triées sur la clé de jointure, sont parcourues ensemble.',
            PlanOperation::Hash => 'Les lignes '.($node->table === null ? '' : "de {$node->table} ").'sont chargées en mémoire, indexées par la clé de jointure.',
            PlanOperation::Group => match (true) {
                $node->facts['temporary'] ?? false => "Les lignes arrivent sans ordre particulier. {$engine} les range dans une table temporaire pour regrouper par ".($key ?? 'la clé de regroupement').'.',
                ($node->facts['strategy'] ?? null) === 'Hashed' => "{$engine} regroupe les lignes par ".($key ?? 'la clé').' dans une table de hachage en mémoire.',
                $key === null => "{$engine} calcule les agrégats sur l'ensemble des lignes.",
                default => "Les lignes arrivent déjà dans l'ordre de {$key} : {$engine} les regroupe au fil de la lecture.",
            },
            PlanOperation::Sort => ($inputRows === null ? 'Les lignes' : 'Les '.PlanFormat::rows($inputRows).' lignes').' sont triées par '.($key ?? 'la clé de tri').' avant d\'être renvoyées. Un index qui commence par les colonnes du filtre puis '.($key ?? 'la clé de tri').' fournirait les lignes déjà dans l\'ordre.',
            PlanOperation::Distinct => "{$engine} élimine les lignes en double".(($node->facts['temporary'] ?? false) ? ' à l\'aide d\'une table temporaire.' : '.'),
            PlanOperation::Limit => "{$engine} s'arrête après les ".($node->facts['limit'] ?? $node->estimatedRows ?? '').' premières lignes.',
            PlanOperation::Subquery => ($node->facts['dependent'] ?? false)
                ? "Cette sous-requête dépend de la ligne en cours : {$engine} la recalcule pour chaque ligne de la requête principale."
                : 'Cette sous-requête est calculée une fois, puis son résultat est réutilisé.',
            PlanOperation::Materialize => 'Le résultat de cette étape est calculé une fois et stocké temporairement pour être relu.',
            PlanOperation::Union => 'Les résultats de plusieurs requêtes sont mis bout à bout.',
            PlanOperation::Other => "Étape « {$node->label} » du moteur, sans équivalent dans les catégories connues.",
        };

        return $sentence.$this->comparisonSentence($plan, $node);
    }

    /**
     * En comparaison, ce qui a changé pour cette étape : une alerte qui reste avec moins de lignes, ou des lignes lues en moins.
     */
    private function comparisonSentence(Plan $plan, PlanNode $node): string
    {
        if ($node->before === null) {
            return '';
        }

        $remaining = array_find($node->alerts, fn (array $alert): bool => ($alert['status'] ?? null) === 'remaining');
        $inputRows = $plan->inputRows($node);
        $inputBefore = $node->before['inputRows'];

        if ($remaining !== null && $inputRows !== null && $inputBefore !== null && $inputRows !== $inputBefore) {
            return ' L\'alerte reste, mais l\'étape ne reçoit plus que '.PlanFormat::rows($inputRows).' lignes au lieu de '.PlanFormat::rows($inputBefore).'.';
        }

        if ($node->operation->isRead() && $node->rowsExamined !== null && $node->before['rowsExamined'] !== null && $node->rowsExamined !== $node->before['rowsExamined']) {
            return ' Avant : '.PlanFormat::rows((int) $node->before['rowsExamined']).' lignes lues ('.mb_strtolower($node->before['label']).').';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return list<array{label: string, value: string, mono?: bool, tone?: string}>
     */
    private function details(Plan $plan, PlanNode $node, array $bindings): array
    {
        $before = $node->before;

        if ($node->operation === PlanOperation::Result) {
            $totals = $plan->totals();
            $alerts = $totals['alerts']['danger'] + $totals['alerts']['warn'];
            $improved = $before !== null && $before['cost'] !== null && $node->cost !== null && $node->cost < $before['cost'];

            return array_values(array_filter([
                $node->cost === null ? null : ['label' => 'Coût total', 'value' => PlanFormat::cost($node->cost), ...($improved ? ['tone' => 'good'] : [])],
                $before === null || $before['cost'] === null ? null : ['label' => 'Avant', 'value' => PlanFormat::cost((float) $before['cost'])],
                ['label' => 'Étapes', 'value' => (string) count($plan->nodes)],
                ['label' => 'Alertes', 'value' => (string) $alerts],
                ...array_map(
                    fn (string $name, mixed $value): array => ['label' => 'Paramètre', 'value' => ':'.$name.' = '.(is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value)), 'mono' => true],
                    array_keys($bindings),
                    $bindings,
                ),
            ]));
        }

        if ($node->operation->isRead()) {
            $possibleKeys = $node->facts['possibleKeys'] ?? null;
            $filtered = $node->facts['filtered'] ?? null;
            $alert = $node->hasAlert('danger');

            return array_values(array_filter([
                isset($node->facts['accessType']) ? ['label' => 'Accès', 'value' => (string) $node->facts['accessType'], 'mono' => true, ...($alert ? ['tone' => 'danger'] : [])] : null,
                $possibleKeys === null ? null : ['label' => 'Index possibles', 'value' => $possibleKeys === [] ? 'aucun' : implode(', ', $possibleKeys), 'mono' => $possibleKeys !== []],
                ['label' => 'Index utilisé', 'value' => $node->index ?? 'aucun', 'mono' => $node->index !== null],
                $node->rowsExamined === null ? null : ['label' => 'Lignes examinées', 'value' => PlanFormat::rows($node->rowsExamined).$this->rowsBefore($before['rowsExamined'] ?? null)],
                $node->estimatedRows === null || $node->estimatedRows === $node->rowsExamined ? null : ['label' => 'Lignes conservées', 'value' => PlanFormat::rows($node->estimatedRows).(is_float($filtered) ? ' ('.number_format($filtered, 2, ',', ' ').' %)' : '')],
                ...$this->costDetails($node),
            ]));
        }

        $inputRows = $plan->inputRows($node);

        return array_values(array_filter([
            isset($node->facts['temporary']) ? ['label' => 'Table temporaire', 'value' => $node->facts['temporary'] ? 'oui' : 'non'] : null,
            isset($node->facts['filesort']) && in_array($plan->driver, ['mysql', 'mariadb'], true) ? ['label' => 'Tri à part (filesort)', 'value' => $node->facts['filesort'] ? 'oui' : 'non'] : null,
            isset($node->facts['joinType']) ? ['label' => 'Type', 'value' => (string) $node->facts['joinType']] : null,
            $inputRows === null ? null : ['label' => 'Lignes en entrée', 'value' => PlanFormat::rows($inputRows).$this->rowsBefore($before['inputRows'] ?? null)],
            $node->estimatedRows === null ? null : ['label' => 'Lignes produites', 'value' => PlanFormat::rows($node->estimatedRows)],
            ...$this->costDetails($node),
        ]));
    }

    /**
     * @return list<array{label: string, value: string, tone?: string}>
     */
    private function costDetails(PlanNode $node): array
    {
        if ($node->selfCost === null) {
            return $node->operation->isRead() || $node->operation === PlanOperation::Result ? [] : [['label' => 'Coût', 'value' => 'inclus dans les étapes du dessous']];
        }

        if ($node->selfCost <= 0 && ! $node->operation->isRead()) {
            return [];
        }

        $beforeCost = $node->before['selfCost'] ?? null;
        $value = PlanFormat::cost($node->selfCost).($node->costShare === null ? '' : ' ('.PlanFormat::share($node->costShare).')');

        if ($beforeCost === null || abs($beforeCost - $node->selfCost) < 0.01) {
            return [['label' => 'Coût propre', 'value' => $value]];
        }

        return [['label' => 'Coût propre', 'value' => $value.' (avant '.PlanFormat::cost((float) $beforeCost).')', 'tone' => $node->selfCost < $beforeCost ? 'good' : 'danger']];
    }

    private function rowsBefore(?int $rows): string
    {
        return $rows === null ? '' : ' (avant '.PlanFormat::rows($rows).')';
    }

    private function hasAlert(PlanNode $node, string $code): bool
    {
        return array_any($node->alerts, fn (array $alert): bool => $alert['code'] === $code);
    }
}
