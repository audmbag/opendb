<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\QueryBindings;
use App\Database\SelectQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ShowChart extends BaseTool implements Tool
{
    public const int MAX_POINTS = 500;

    public const array CHART_TYPES = ['bar', 'line', 'area', 'pie'];

    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Displays a chart (bars, lines, areas or pie) in the interface, as an artifact, from '
            .'an aggregation SELECT query. This tool does not return the data to you. '
            .'The query must return an x-axis column `xColumn` (label, date or category) and one or '
            .'several numeric columns `yColumns`, sorted in display order (ORDER BY), with at most '
            .self::MAX_POINTS.' rows. `chartType`: line/area for a trend over time, bar to compare '
            .'categories, pie for a breakdown (a single column in `yColumns`, 8 categories at most). '
            .'`stacked` stacks the series (bar and area).';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $query = new SelectQuery($this->driver, (string) $request['query'], QueryBindings::from($request['bindings'] ?? null));
            $chartType = (string) $request['chartType'];
            $xColumn = (string) $request['xColumn'];
            $yColumns = array_values((array) ($request['yColumns'] ?? []));

            if (! in_array($chartType, self::CHART_TYPES, true)) {
                throw new RuntimeException('`chartType` must be one of '.implode(', ', self::CHART_TYPES).'.');
            }

            if ($yColumns === []) {
                throw new RuntimeException('`yColumns` must hold at least one numeric column.');
            }

            $columns = $query->columns();
            $missingColumns = $columns === [] ? [] : array_diff([$xColumn, ...$yColumns], $columns);

            if ($missingColumns !== []) {
                throw new RuntimeException('Columns missing from the query result: '.implode(', ', $missingColumns).'. Available columns: '.implode(', ', $columns).'.');
            }

            return [
                'title' => $request['title'] ?? null,
                'chartType' => $chartType,
                'xColumn' => $xColumn,
                'yColumns' => $yColumns,
                'stacked' => (bool) ($request['stacked'] ?? false),
                'totalRows' => $count = $query->count(),
                'truncated' => $count > self::MAX_POINTS,
            ];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->selectQuerySchema($schema),
            'chartType' => $schema->string()->enum(self::CHART_TYPES)->required()->description('Chart type.'),
            'xColumn' => $schema->string()->required()->description('Column of the result used as the x-axis (or as the category for a pie chart).'),
            'yColumns' => $schema->array()->items($schema->string())->min(1)->required()->description('Numeric columns of the result displayed as series.'),
            'title' => $schema->string()->nullable()->description('Short title of the chart.'),
            'stacked' => $schema->boolean()->default(false)->description('Stacks the series (bar and area only).'),
        ];
    }
}
