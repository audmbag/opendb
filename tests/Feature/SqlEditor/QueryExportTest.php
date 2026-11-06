<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-export-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    $this->exportUrl = "/{$this->project->id}/sql-editor/export";
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('streams every row of the query, named after the file of the tab', function (string $format, string $expected) {
    $response = $this->actingAs($this->user)->post($this->exportUrl, [
        'sql' => "-- clients\nSELECT name, city FROM customers ORDER BY id;",
        'format' => $format,
        'name' => 'Ventes été.sql',
    ]);

    $response->assertOk()->assertDownload("ventes-ete.{$format}");
    expect($response->streamedContent())->toBe($expected);
})->with([
    'csv' => ['csv', "name,city\nAda,Paris\nGrace,Lyon\nAlan,\n"],
    'json' => ['json', "[\n  {\"name\":\"Ada\",\"city\":\"Paris\"},\n  {\"name\":\"Grace\",\"city\":\"Lyon\"},\n  {\"name\":\"Alan\",\"city\":null}\n]"],
]);

it('exports all the rows, beyond the thousand shown by the console', function () {
    $connection = $this->project->driver_config->getDriver()->getConnection();
    $connection->table('customers')->insert(array_map(fn (int $index): array => ['name' => "client {$index}"], range(1, 1200)));

    $response = $this->actingAs($this->user)->post($this->exportUrl, ['sql' => 'SELECT id FROM customers', 'format' => 'csv']);

    expect(substr_count($response->streamedContent(), "\n"))->toBe(1 + 1203);
});

it('refuses a statement that is not a single SELECT, and changes nothing', function (string $sql) {
    $this->actingAs($this->user)
        ->postJson($this->exportUrl, ['sql' => $sql, 'format' => 'csv'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query']);

    expect($this->project->driver_config->getDriver()->getConnection()->table('orders')->where('status', 'paid')->count())->toBe(2);
})->with([
    'update' => "UPDATE orders SET status = 'paid'",
    'two statements' => "SELECT 1; UPDATE orders SET status = 'paid'",
]);

it('reports a query refused by the database instead of downloading an empty file', function () {
    $this->actingAs($this->user)
        ->postJson($this->exportUrl, ['sql' => 'SELECT * FROM invoices', 'format' => 'csv'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.query.0', fn (string $message) => str_contains($message, 'invoices'));
});

it('rejects an unknown format', function () {
    $this->actingAs($this->user)
        ->postJson($this->exportUrl, ['sql' => 'SELECT 1', 'format' => 'xml'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['format']);
});

it('keeps the export of a project away from other users', function () {
    $this->actingAs(User::factory()->create())
        ->postJson($this->exportUrl, ['sql' => 'SELECT * FROM customers', 'format' => 'csv'])
        ->assertForbidden();
});

it('does not keep exports in the history', function () {
    $this->actingAs($this->user)->post($this->exportUrl, ['sql' => 'SELECT 1', 'format' => 'csv'])->streamedContent();

    expect(DB::table('query_history_entries')->count())->toBe(0);
});
