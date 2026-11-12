<?php

use App\Mcp\McpToolbox;
use App\Mcp\Servers\OpenDbServer;
use App\Models\McpToken;
use App\Models\User;
use App\Services\Mcp\Data\McpSession;
use App\Services\Mcp\McpAccessManager;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Server\Tool;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('mcp-server-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    McpToken::factory()->for($this->user)->for($this->project)->withPlainToken('odb_test')->create();
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * Start a session as mcp:serve does once the token is checked.
 */
function startMcpSession(): McpSession
{
    $session = app(McpAccessManager::class)->authenticate('odb_test');
    app()->instance(McpSession::class, $session);

    return $session;
}

function mcpTool(string $name): Tool
{
    return collect(app(McpToolbox::class)->for(app(McpSession::class)))->sole(fn (Tool $tool): bool => $tool->name() === $name);
}

it('serves only the tools that read the database', function () {
    $names = array_map(fn (Tool $tool): string => $tool->name(), app(McpToolbox::class)->for(startMcpSession()));

    expect($names)->toBe(['list_tables', 'focus_table', 'database_schema', 'read_query', 'explain_query', 'profile_table']);
});

it('runs a read query on the database of the project', function () {
    startMcpSession();

    OpenDbServer::tool(mcpTool('read_query'), ['query' => 'SELECT name FROM customers WHERE city = :city', 'bindings' => ['city' => 'Lyon']])
        ->assertOk()
        ->assertSee('Grace')
        ->assertDontSee('Ada');
});

it('refuses a query that changes the database, as an error of the tool', function () {
    startMcpSession();

    OpenDbServer::tool(mcpTool('read_query'), ['query' => 'DELETE FROM orders', 'bindings' => []])
        ->assertHasErrors(['Requête rejetée : seules les instructions SELECT, WITH, EXPLAIN, SHOW et DESCRIBE sont autorisées']);

    expect($this->project->driver_config->getDriver()->executeReadOnly('SELECT COUNT(*) AS total FROM orders')[0]->total)->toBe(3);
});

it('keeps the tables hidden from the agent out of reach', function () {
    $this->project->driver_config->update(['disabled_tables' => ['customers']]);
    startMcpSession();

    OpenDbServer::tool(mcpTool('list_tables'))->assertOk()->assertDontSee('customers');
    OpenDbServer::tool(mcpTool('focus_table'), ['tableName' => 'customers'])
        ->assertHasErrors(["La table [customers] est désactivée pour l'agent"]);
    OpenDbServer::tool(mcpTool('profile_table'), ['tableName' => 'customers'])
        ->assertHasErrors(["La table [customers] est désactivée pour l'agent"]);
});

it('returns the data profile of a table', function () {
    startMcpSession();

    OpenDbServer::tool(mcpTool('profile_table'), ['tableName' => 'orders'])
        ->assertOk()
        ->assertSee(['"table":"orders"', '"totalRows":3', '"name":"status"']);
});

it('logs every call of the external agent', function () {
    $session = startMcpSession();
    Log::shouldReceive('channel')->with('mcp-logs')->once()->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $context['token'] === $session->token->id
        && $context['user'] === $this->user->name
        && $context['tool'] === 'list_tables'
        && $context['failed'] === false);

    OpenDbServer::tool(mcpTool('list_tables'))->assertOk();
});
