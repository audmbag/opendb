<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('tool-artifact-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * A conversation whose agent answered by calling the given tool with the given arguments.
 *
 * @param  array<string, mixed>  $arguments
 */
function conversationWithArtifact(User $user, Project $project, string $tool, array $arguments): Conversation
{
    return conversationWithToolCall($user, $project, [
        'id' => 'call_1',
        'name' => $tool,
        'arguments' => $arguments,
        'result' => '{"success":true}',
    ], MessageStatus::Completed);
}

it('returns a sorted page of the rows of the query', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowQueryResults', [
        'query' => 'SELECT id, total FROM orders WHERE status IN (:paid, :pending)',
        'bindings' => ['paid' => 'paid', 'pending' => 'pending'],
    ]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/results?page=2&perPage=10&sort=total&direction=desc");

    $response->assertOk()->assertExactJson(['data' => [
        'columns' => ['id', 'total'],
        'rows' => [],
        'total' => 3,
        'page' => 2,
        'perPage' => 10,
    ]]);

    $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/results?sort=total&direction=desc")
        ->assertJsonPath('data.rows', [['id' => 1, 'total' => 120], ['id' => 2, 'total' => 80], ['id' => 3, 'total' => 50]]);
});

it('refuses to sort the rows on a column the query does not return', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowQueryResults', ['query' => 'SELECT id FROM orders', 'bindings' => []]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/results?sort=".urlencode('id; DROP TABLE orders'));

    $response->assertUnprocessable();
    expect($this->project->driver_config->getDriver()->getConnection()->table('orders')->count())->toBe(3);
});

it('exports all the rows of the query as CSV', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowQueryResults', ['query' => 'SELECT name, city FROM customers ORDER BY id', 'bindings' => []]);

    $response = $this->actingAs($this->user)->get("/conversations/{$conversation->id}/tool-calls/call_1/artifact/results?format=csv");

    $response->assertOk()->assertDownload('resultats.csv');
    expect($response->streamedContent())->toBe("name,city\nAda,Paris\nGrace,Lyon\nAlan,\n");
});

it('returns 404 when the conversation belongs to another user', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowQueryResults', ['query' => 'SELECT * FROM customers', 'bindings' => []]);

    $response = $this->actingAs(User::factory()->create())->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/results");

    $response->assertNotFound();
});

it('returns 404 when the call was made by another tool than the one of the artifact', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ReadQuery', ['query' => 'SELECT * FROM customers', 'bindings' => []]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/results");

    $response->assertNotFound();
});

it('computes the value of each card and the error of a card whose query does not return a single value', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowKpis', ['kpis' => [
        ['label' => 'Chiffre d\'affaires', 'query' => 'SELECT SUM(total) FROM orders', 'bindings' => [], 'format' => 'currency', 'currency' => 'usd'],
        ['label' => 'Statuts', 'query' => 'SELECT status FROM orders', 'bindings' => [], 'format' => 'number', 'unit' => 'statuts'],
    ]]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/kpis");

    $response->assertOk()->assertExactJson(['data' => [
        ['label' => 'Chiffre d\'affaires', 'format' => 'currency', 'currency' => 'USD', 'unit' => null, 'value' => 250, 'error' => null],
        ['label' => 'Statuts', 'format' => 'number', 'currency' => null, 'unit' => 'statuts', 'value' => null, 'error' => 'La requête doit renvoyer une seule valeur (une ligne, une colonne).'],
    ]]);
});

it('returns the points of the chart in the order of the query', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowChart', [
        'query' => 'SELECT status, SUM(total) AS revenue FROM orders GROUP BY status ORDER BY revenue DESC',
        'bindings' => [],
        'chartType' => 'bar',
        'xColumn' => 'status',
        'yColumns' => ['revenue'],
    ]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/chart");

    $response->assertOk()->assertExactJson(['data' => [
        'rows' => [['status' => 'paid', 'revenue' => 170], ['status' => 'pending', 'revenue' => 80]],
        'truncated' => false,
    ]]);
});

it('profiles how each column of the table is filled', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ProfileTable', ['tableName' => 'customers']);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/profile");

    $response->assertOk()
        ->assertJsonPath('data.totalRows', 3)
        ->assertJsonPath('data.columns.0.min', 1)
        ->assertJsonPath('data.columns.0.max', 3)
        ->assertJsonPath('data.columns.0.averageLength', null)
        ->assertJsonPath('data.columns.0.minLength', null)
        ->assertJsonPath('data.columns.2', [
            'name' => 'city',
            'type' => 'varchar',
            'key' => null,
            'kind' => 'text',
            'nullCount' => 1,
            'nullRatio' => 0.3333,
            'distinctCount' => 2,
            'min' => null,
            'max' => null,
            'averageLength' => 4.5,
            'minLength' => 4,
            'maxLength' => 5,
            'topValues' => [['value' => 'Lyon', 'count' => 1], ['value' => 'Paris', 'count' => 1]],
        ]);
});

it('tells which key each column forms on its own', function () {
    $this->project->driver_config->getDriver()->getConnection()->getSchemaBuilder()->table('orders', function (Blueprint $table) {
        $table->unique('total');
        $table->unique(['customer_id', 'status']);
    });
    $conversation = conversationWithArtifact($this->user, $this->project, 'ProfileTable', ['tableName' => 'orders']);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/profile");

    expect(array_column($response->assertOk()->json('data.columns'), 'key', 'name'))->toBe([
        'id' => 'PK',
        'customer_id' => 'FK',
        'total' => 'UQ',
        'status' => null,
    ]);
});

it('returns the record with the rows it references and the number of rows referencing it', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowRecord', ['tableName' => 'customers', 'key' => ['id' => 1]]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/record");

    $response->assertOk()->assertExactJson(['data' => [
        'table' => 'customers',
        'primaryKey' => ['id'],
        'key' => ['id' => 1],
        'values' => ['id' => 1, 'name' => 'Ada', 'city' => 'Paris'],
        'outgoing' => [],
        'incoming' => [['table' => 'orders', 'columns' => ['customer_id'], 'key' => ['customer_id' => 1], 'count' => 2]],
    ]]);
});

it('navigates from the record to a row it references', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowRecord', ['tableName' => 'orders', 'key' => ['id' => 3]]);

    $record = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/record");
    $record->assertJsonPath('data.outgoing', [['columns' => ['customer_id'], 'table' => 'customers', 'key' => ['id' => 2]]]);

    $related = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/record?".http_build_query(['table' => 'customers', 'key' => ['id' => 2]]));

    $related->assertOk()->assertJsonPath('data.values', ['id' => 2, 'name' => 'Grace', 'city' => 'Lyon']);
});

it('returns 404 when navigating to a table missing from the database', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ShowRecord', ['tableName' => 'orders', 'key' => ['id' => 3]]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/record?".http_build_query(['table' => 'invoices', 'key' => ['id' => 1]]));

    $response->assertNotFound();
});

it('returns the execution plan of the query as a tree', function () {
    $conversation = conversationWithArtifact($this->user, $this->project, 'ExplainQuery', ['query' => 'SELECT * FROM orders WHERE status = :status', 'bindings' => ['status' => 'paid']]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/explain");

    $response->assertOk()->assertExactJson(['data' => [
        ['label' => 'SCAN orders', 'detail' => null, 'cost' => null, 'rows' => null, 'warning' => 'Parcours complet de la table', 'children' => []],
    ]]);
});

it('counts the rows a pending write would change without changing them', function () {
    $conversation = conversationWithToolCall($this->user, $this->project, [
        'id' => 'call_1',
        'name' => 'WriteQuery',
        'arguments' => ['query' => 'DELETE FROM orders WHERE status = :status', 'bindings' => ['status' => 'paid']],
        'approval_reason' => 'Opération destructrice.',
    ]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/write-preview");

    $response->assertOk()->assertExactJson(['data' => [
        'query' => 'DELETE FROM orders WHERE status = :status',
        'affectedRows' => 2,
    ]]);
    expect($this->project->driver_config->getDriver()->getConnection()->table('orders')->count())->toBe(3);
});

it('does not try a pending write that is not an update or a delete', function () {
    $conversation = conversationWithToolCall($this->user, $this->project, [
        'id' => 'call_1',
        'name' => 'WriteQuery',
        'arguments' => ['query' => 'DROP TABLE orders', 'bindings' => []],
        'approval_reason' => 'Opération destructrice.',
    ]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/write-preview");

    $response->assertOk()->assertJsonPath('data.affectedRows', null);
    expect($this->project->driver_config->getDriver()->getConnection()->getSchemaBuilder()->hasTable('orders'))->toBeTrue();
});

it('returns 404 for the preview of a write that is no longer waiting for approval', function () {
    $conversation = conversationWithToolCall($this->user, $this->project, [
        'id' => 'call_1',
        'name' => 'WriteQuery',
        'arguments' => ['query' => 'DELETE FROM orders', 'bindings' => []],
        'approval_reason' => 'Opération destructrice.',
        'result' => 'Requête exécutée avec succès.',
    ]);

    $response = $this->actingAs($this->user)->getJson("/conversations/{$conversation->id}/tool-calls/call_1/artifact/write-preview");

    $response->assertNotFound();
});

describe('download', function () {
    beforeEach(function () {
        $this->projectPath = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.uniqid('tool-artifact-project-');
        mkdir($this->projectPath.'/exports', recursive: true);
        file_put_contents($this->projectPath.'/exports/shop_20261025_101800.zip', 'archive');
        file_put_contents($this->projectPath.'/.env', 'APP_KEY=secret');
        $this->project->update(['path' => $this->projectPath]);
    });

    afterEach(function () {
        File::deleteDirectory($this->projectPath);
    });

    function conversationWithExport(User $user, Project $project, string $path): Conversation
    {
        return conversationWithToolCall($user, $project, [
            'id' => 'call_1',
            'name' => 'ExportDatabase',
            'arguments' => [],
            'result' => json_encode(['success' => true, 'path' => $path, 'format' => 'sql', 'output' => 'zip', 'tables' => 'all', 'bytes' => 7]),
        ], MessageStatus::Completed);
    }

    it('downloads the archive written by the export', function () {
        $conversation = conversationWithExport($this->user, $this->project, 'exports/shop_20261025_101800.zip');

        $response = $this->actingAs($this->user)->get("/conversations/{$conversation->id}/tool-calls/call_1/artifact/download");

        $response->assertOk()->assertDownload('shop_20261025_101800.zip');
    });

    it('returns 404 for a file outside the exports directory of the project', function (string $path) {
        $conversation = conversationWithExport($this->user, $this->project, $path);

        $this->actingAs($this->user)->get("/conversations/{$conversation->id}/tool-calls/call_1/artifact/download")->assertNotFound();
    })->with([
        'project file' => ['.env'],
        'path traversal' => ['exports/../.env'],
        'missing archive' => ['exports/missing.zip'],
    ]);

    it('returns 404 when the conversation belongs to another user', function () {
        $conversation = conversationWithExport($this->user, $this->project, 'exports/shop_20261025_101800.zip');

        $this->actingAs(User::factory()->create())->get("/conversations/{$conversation->id}/tool-calls/call_1/artifact/download")->assertNotFound();
    });
});
