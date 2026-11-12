<?php

namespace App\Mcp\Tools;

use App\Database\TableProfiler;
use App\Mcp\Tools\Concerns\LogsMcpCalls;
use App\Services\Mcp\Data\McpSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

/**
 * The data profile of a table, returned to the external agent: the tool of the agent of OpenDB only draws it in the
 * interface.
 */
#[IsReadOnly]
class ProfileTable extends Tool
{
    use LogsMcpCalls;

    protected string $name = 'profile_table';

    public function __construct(private readonly McpSession $session) {}

    public function description(): string
    {
        return 'Returns the data profile of a table: for each column, the number and the rate of NULL values, the number '
            .'of distinct values, the minimum and the maximum, the length of the texts and the most frequent values '
            .'(computed on a sample of '.TableProfiler::SAMPLE_SIZE.' rows). Use it to judge the quality or the '
            .'distribution of the data of a table. The `tableName` parameter is the exact name of the table.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tableName' => $schema->string()
                ->required()
                ->description('The exact name of the table to profile.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->logged($this->session, $request->all(), function () use ($request): Response {
            try {
                $profile = new TableProfiler($this->session->driver, (string) $request->get('tableName'))->profile();
            } catch (QueryException $e) {
                return Response::error($e->getPrevious()?->getMessage() ?? $e->getMessage());
            } catch (RuntimeException $e) {
                return Response::error($e->getMessage());
            }

            return Response::text(json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        });
    }
}
