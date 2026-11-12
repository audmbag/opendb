<?php

namespace App\Ai\Tools\Database;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Database\AdminStatementGuard;
use App\Database\Contracts\DriverContract;
use App\Database\HiddenTableGuard;
use App\Database\QueryBindings;
use App\Database\StackedStatementGuard;
use App\Support\TranslatableMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class WriteQuery extends BaseTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * @var list<string> The destructive keywords, each explained by ai.approvals.write_query.{keyword} in lang/{locale}/ai.php.
     */
    private const array DESTRUCTIVE_OPERATIONS = ['DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'UPDATE'];

    public function __construct(
        private readonly DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Runs an SQL query that changes data (INSERT, UPDATE, DELETE, CREATE, ALTER, TRUNCATE) on the current '
            .'database. Before dropping a table, first check whether it is empty, and if it is, tell the user '
            .'clearly. Multiple queries (stacked statements) are not allowed. The operations reserved to an '
            .'administrator (accounts, roles, privileges, databases, server) are always refused. Important note: '
            .'when inserting values that are usually hashed, like a password, first check whether the user gave a '
            .'clear-text password and, if so, hash it before inserting it in the database. For deletions (DELETE), '
            .'always use a WHERE clause on the primary key if there is one, otherwise on a unique identifier, and '
            .'never on a field that can hold duplicate values.';
    }

    public function handle(Request $request): Stringable|string
    {
        $sql = trim((string) $request['query']);

        try {
            $this->assertAllowed($sql);

            $this->driver->statement($sql, QueryBindings::from($request['bindings'] ?? null));
        } catch (QueryException $e) {
            return $this->formatQueryError($e);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return 'Query run successfully.';
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->getSchemaForQueryByAccessMode(AgentAccessModeEnum::ReadWrite, $schema);
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $raw_sql = (string) $request['query'];

        if (AdminStatementGuard::isAdministrative($raw_sql)
            || StackedStatementGuard::hasSeveralStatements($raw_sql)
            || HiddenTableGuard::rejects($raw_sql, $this->driver->hiddenTables())) {
            return false;
        }

        $sql = strtoupper(StackedStatementGuard::withoutTextValues($raw_sql));

        foreach (self::DESTRUCTIVE_OPERATIONS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/', $sql)) {
                return Approval::required($this->approvalReason($sql, $keyword)->toJson());
            }
        }

        return false;
    }

    /**
     * Explain the operation from the keyword starting the statement, since the matched keyword may only be a part of it (ON DELETE CASCADE, ALTER … DROP COLUMN).
     * Any other query gets a warning that does not claim what it does. Saved with the conversation, the reason is shown
     * in the language of the interface whenever it is displayed.
     */
    private function approvalReason(string $sql, string $matchedKeyword): TranslatableMessage
    {
        preg_match('/^\s*(\w+)/', $sql, $matches);

        $statement = mb_strtolower($matches[1] ?? '');

        return in_array(mb_strtoupper($statement), self::DESTRUCTIVE_OPERATIONS, true)
            ? new TranslatableMessage("ai.approvals.write_query.{$statement}")
            : new TranslatableMessage('ai.approvals.write_query.other', ['keyword' => $matchedKeyword]);
    }

    private function assertAllowed(string $sql): void
    {
        AdminStatementGuard::assertNotAdministrative($sql);
        StackedStatementGuard::assertSingleStatement($sql);
    }
}
