<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\TableRecordForm;
use App\Support\TranslatableMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateRecord extends BaseTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        private readonly DriverContract $driver,
    ) {}

    /**
     * The cache key of the values the user submitted in the form of a tool call.
     */
    public static function submissionCacheKey(string $toolCallId): string
    {
        return "form-submission:{$toolCallId}";
    }

    public function description(): Stringable|string
    {
        return 'Prepares the creation of a record in a table. NEVER write an INSERT query yourself to create a '
            .'record: always use this tool with the name of the target table. An interactive form, generated from '
            .'the structure of the table, will be shown to the user; do not make up values for the fields the user '
            .'has not given yet. Once the record is created, always give the user its details: the table, the '
            .'generated identifier if there is one, and each filled column with its value, as returned by this '
            .'tool (as a list or a table). If the user cancelled the form or if the creation failed, say it '
            .'clearly with the reason.';
    }

    public function handle(Request $request): Stringable|string
    {
        $table = (string) $request->string('table');

        $submission = Cache::pull(static::submissionCacheKey((string) $request->toolCallId()));

        if (! is_array($submission) || $submission['table'] !== $table) {
            return 'No form data was submitted for this call: the record was not created.';
        }

        try {
            $record = new TableRecordForm($this->driver, $table)->insert($submission['values']);
        } catch (HiddenTableException|TableNotFoundException $e) {
            return $e->getMessage();
        } catch (QueryException $e) {
            return $this->formatQueryError($e);
        }

        return "Record created in `{$table}`: ".$this->toJson($record);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()
                ->description('Exact name of the target table, as found through ListTables.')
                ->required(),
            'known_values' => $schema->object(fn ($schema) => [])
                ->description('Values the user already gave in the conversation, to prefill in the form, keyed by column name.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required((new TranslatableMessage('ai.approvals.form_input'))->toJson());
    }
}
