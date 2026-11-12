<?php

namespace App\Services\ToolArtifact;

use App\Ai\Tools\Database\ShowKpis;
use App\Database\Contracts\DriverContract;
use App\Database\QueryBindings;
use App\Database\SelectQuery;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Computes the value of the metric cards of a ShowKpis call. A card whose query fails keeps its error, so the other
 * cards are still displayed.
 */
class KpiEvaluator
{
    /**
     * @param  list<array<string, mixed>>  $kpis  the cards as the agent described them
     * @return list<array{label: string, format: string, currency: ?string, unit: ?string, value: mixed, error: ?string}>
     */
    public function evaluate(DriverContract $driver, array $kpis): array
    {
        return array_map(fn (array $kpi): array => $this->card($driver, $kpi), $kpis);
    }

    /**
     * @param  array<string, mixed>  $kpi
     * @return array{label: string, format: string, currency: ?string, unit: ?string, value: mixed, error: ?string}
     */
    private function card(DriverContract $driver, array $kpi): array
    {
        $card = [
            'label' => (string) ($kpi['label'] ?? ''),
            'format' => in_array($kpi['format'] ?? null, ShowKpis::FORMATS, true) ? $kpi['format'] : 'number',
            'currency' => ShowKpis::currencyOf($kpi),
            'unit' => $kpi['unit'] ?? null,
            'value' => null,
            'error' => null,
        ];

        try {
            $rows = new SelectQuery($driver, (string) ($kpi['query'] ?? ''), QueryBindings::from($kpi['bindings'] ?? null))->rows(2);
        } catch (QueryException $e) {
            return [...$card, 'error' => $e->getPrevious()?->getMessage() ?? $e->getMessage()];
        } catch (RuntimeException $e) {
            return [...$card, 'error' => $e->getMessage()];
        }

        if (count($rows) !== 1 || count($rows[0]) !== 1) {
            return [...$card, 'error' => __('ai.artifacts.single_value')];
        }

        return [...$card, 'value' => array_values($rows[0])[0]];
    }
}
