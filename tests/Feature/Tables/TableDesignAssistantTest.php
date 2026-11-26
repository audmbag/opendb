<?php

use App\Ai\Agents\Tables\TableDesignAgent;
use App\Ai\Tools\Tables\ProposeTableDesign;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Schema\Builder;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('table-design-assistant-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, fn (Builder $schema) => shopDatabase($schema));

    Provider::query()->updateOrCreate(['id' => 'anthropic'], [
        'label' => 'Anthropic',
        'api_key' => 'stored-anthropic-key',
        'api_url' => 'https://anthropic.test/v1',
        'models' => [['id' => 'claude-opus-5-5', 'label' => 'Claude Opus 5.5', 'context_window' => 1000000, 'active' => true]],
    ]);
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * @param  array<string, mixed>  $data
 */
function askTableDesigner(array $data = [])
{
    return test()->actingAs(test()->user)->postJson('/'.test()->project->id.'/tables/ai/design', [
        'provider' => 'anthropic',
        'model' => 'claude-opus-5-5',
        'prompt' => 'Une table des factures de chaque client',
        ...$data,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function proposedInvoices(array $overrides = []): array
{
    return [
        'table' => 'invoices',
        'columns' => [
            ['name' => 'id', 'type' => 'bigInteger', 'unsigned' => true, 'autoIncrement' => true],
            ['name' => 'customer_id', 'type' => 'bigInteger', 'unsigned' => true],
            ['name' => 'number', 'type' => 'string', 'length' => 40],
            ['name' => 'issued_at', 'type' => 'date', 'nullable' => true],
        ],
        'indexes' => [['columns' => ['number'], 'unique' => true]],
        'foreignKeys' => [['columns' => ['customer_id'], 'referencesTable' => 'customers', 'referencesColumns' => ['id'], 'onDelete' => 'cascade']],
        ...$overrides,
    ];
}

it('proposes the whole table from a sentence, checked by the rules of the designer, without creating it', function () {
    $this->project->driver_config->update(['disabled_tables' => ['orders']]);
    TableDesignAgent::fake([new ToolCall('call_1', 'ProposeTableDesign', proposedInvoices()), 'Les factures, reliées à leur client.']);

    $response = askTableDesigner(['form' => ['name' => 'invoices', 'columns' => [['name' => 'id', 'type' => 'bigInteger']]]]);

    $response->assertOk()
        ->assertJsonPath('data.explanation', 'Les factures, reliées à leur client.')
        ->assertJsonPath('data.design.name', 'invoices')
        ->assertJsonPath('data.design.primaryKey', ['id'])
        ->assertJsonPath('data.design.columns.2.length', 40)
        ->assertJsonPath('data.design.columns.3.nullable', true)
        ->assertJsonPath('data.design.indexes.0.unique', true)
        ->assertJsonPath('data.design.foreignKeys.0.referencesTable', 'customers')
        ->assertJsonPath('data.design.foreignKeys.0.onDelete', 'cascade');
    expect(array_column($response->json('data.design.columns'), 'name'))->toBe(['id', 'customer_id', 'number', 'issued_at'])
        ->and($this->project->driver_config->getDriver()->getConnection()->getSchemaBuilder()->hasTable('invoices'))->toBeFalse();
    TableDesignAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Une table des factures de chaque client'
        && $prompt->agent->tables === ['customers']
        && json_decode($prompt->agent->form, true) === ['name' => 'invoices', 'columns' => [['name' => 'id', 'type' => 'bigInteger']]]);
});

it('keeps the last proposal that follows the rules', function () {
    TableDesignAgent::fake([
        new ToolCall('call_1', 'ProposeTableDesign', proposedInvoices()),
        new ToolCall('call_2', 'ProposeTableDesign', proposedInvoices(['table' => 'customers'])),
        'Les factures.',
    ]);

    askTableDesigner()->assertOk()->assertJsonPath('data.design.name', 'invoices');
});

it('leaves out the plain indexes that the primary key or another index already covers', function () {
    TableDesignAgent::fake([new ToolCall('call_1', 'ProposeTableDesign', proposedInvoices(['indexes' => [
        ['columns' => ['customer_id', 'number'], 'unique' => true],
        ['columns' => ['customer_id']],
        ['columns' => ['id']],
        ['columns' => ['issued_at']],
        ['columns' => ['issued_at']],
        ['columns' => ['number', 'issued_at']],
    ]])), 'Les factures.']);

    $indexes = askTableDesigner()->assertOk()->json('data.design.indexes');

    expect(array_map(fn (array $index): array => [$index['columns'], $index['unique']], $indexes))->toBe([
        [['customer_id', 'number'], true],
        [['issued_at'], false],
        [['number', 'issued_at'], false],
    ]);
});

it('gives the reason of the agent when it proposes no table', function () {
    TableDesignAgent::fake(['La table customers contient déjà les clients.']);

    askTableDesigner(['prompt' => 'Une table des clients'])
        ->assertOk()
        ->assertExactJson(['data' => ['design' => null, 'explanation' => 'La table customers contient déjà les clients.']]);
});

it('explains that the agent proposed no table that follows the rules', function () {
    TableDesignAgent::fake([
        new ToolCall('call_1', 'ProposeTableDesign', proposedInvoices(['foreignKeys' => [['columns' => ['customer_id'], 'referencesTable' => 'suppliers', 'referencesColumns' => ['id']]]])),
        '',
    ]);

    askTableDesigner()
        ->assertUnprocessable()
        ->assertJsonPath('errors.query.0', "L'assistant n'a pas proposé de table. Reformulez la demande.");
});

it('needs a sentence to propose a table', function () {
    TableDesignAgent::fake();

    askTableDesigner(['prompt' => ''])->assertUnprocessable()->assertJsonValidationErrors('prompt');

    TableDesignAgent::assertNeverPrompted();
});

it('describes to the agent the table it proposes, column by column', function () {
    $schema = new ObjectType(new ProposeTableDesign($this->project->driver_config->getDriver())->schema(new JsonSchemaTypeFactory))->toArray();

    expect($schema['required'])->toBe(['table', 'columns'])
        ->and(array_keys($schema['properties']))->toBe(['table', 'columns', 'primaryKey', 'indexes', 'foreignKeys'])
        ->and($schema['properties']['columns']['items']['properties'])->toHaveKeys(['name', 'type', 'nullable', 'autoIncrement'])
        ->and($schema['properties']['foreignKeys']['items']['required'])->toBe(['columns', 'referencesTable', 'referencesColumns']);
});
