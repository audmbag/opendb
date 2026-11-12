<?php

namespace App\Console\Commands\Chat\Tools;

use App\Ai\Tools\Database\ExplainQuery;
use App\Ai\Tools\Database\ExportDatabase;
use App\Ai\Tools\Database\ProfileTable;
use App\Ai\Tools\Database\ReadQuery;
use App\Ai\Tools\Database\ShowChart;
use App\Ai\Tools\Database\ShowKpis;
use App\Ai\Tools\Database\ShowQueryResults;
use App\Ai\Tools\Database\ShowRecord;
use App\Ai\Tools\Database\ShowSchemaDiagram;
use App\Ai\Tools\Database\WriteQuery;
use Illuminate\Contracts\Container\Container;

/**
 * Which renderer shows which tool of the agent, and what the tool is doing while it runs.
 */
class ToolRendererRegistry
{
    /**
     * @var array<class-string, class-string<ToolRenderer>>
     */
    private const array RENDERERS = [
        ReadQuery::class => SqlToolRenderer::class,
        WriteQuery::class => SqlToolRenderer::class,
        ShowQueryResults::class => QueryResultsRenderer::class,
        ShowKpis::class => KpisRenderer::class,
        ShowChart::class => ChartRenderer::class,
        ProfileTable::class => ProfileRenderer::class,
        ShowRecord::class => RecordRenderer::class,
        ExplainQuery::class => ExplainRenderer::class,
        ShowSchemaDiagram::class => SchemaDiagramRenderer::class,
        ExportDatabase::class => ExportRenderer::class,
    ];

    /**
     * What each tool is doing, as the web chat tells it: the keys of ai.activities in lang/{locale}/ai.php.
     *
     * @var array<string, string>
     */
    private const array ACTIVITIES = [
        'DatabaseSchema' => 'reading_schema',
        'ListTables' => 'listing_tables',
        'ShowSchemaDiagram' => 'creating_diagram',
        'CreateRecord' => 'creating_record',
        'FocusTable' => 'inspecting_table',
        'ReadQuery' => 'running_query',
        'UpdateProjectMemory' => 'updating_memory',
        'WriteQuery' => 'writing_data',
        'ShowQueryResults' => 'preparing_results',
        'ShowKpis' => 'computing_kpis',
        'ShowChart' => 'creating_chart',
        'ProfileTable' => 'profiling_table',
        'ShowRecord' => 'loading_record',
        'ExplainQuery' => 'explaining_query',
        'ExportDatabase' => 'exporting_database',
        'SendMail' => 'sending_mail',
        'ManageMailRecipients' => 'updating_recipients',
        'ProposeSchemaChanges' => 'designing_schema',
    ];

    public function __construct(private readonly Container $container) {}

    public function for(string $tool): ToolRenderer
    {
        $renderer = array_find(self::RENDERERS, fn (string $renderer, string $class): bool => class_basename($class) === $tool);

        return $this->container->make($renderer ?? ToolRenderer::class);
    }

    public function activity(string $tool): string
    {
        return __('ai.activities.'.(self::ACTIVITIES[$tool] ?? 'running_tool'));
    }
}
