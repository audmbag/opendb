<?php

use App\Models\QueryPlan;
use App\Models\User;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-explain-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    $this->explainUrl = "/{$this->project->id}/sql-editor/explain";
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('returns the plan of a SELECT as the chat shows it, without comparing it to another', function () {
    $this->actingAs($this->user)
        ->postJson($this->explainUrl, ['sql' => "SELECT * FROM orders WHERE status = 'paid';"])
        ->assertOk()
        ->assertJsonPath('data.driver', 'sqlite')
        ->assertJsonPath('data.nodes', fn (array $nodes) => in_array('orders', array_column($nodes, 'table'), true))
        ->assertJsonPath('data.totals.alerts.danger', 1)
        ->assertJsonPath('data.comparison', null)
        ->assertJsonPath('data.planId', fn (string $id) => str_starts_with($id, 'qp_'));

    expect(QueryPlan::sole())
        ->project_id->toBe($this->project->id)
        ->conversation_id->toBeNull();
});

it('refuses a statement that is not a SELECT, and runs nothing', function () {
    $this->actingAs($this->user)
        ->postJson($this->explainUrl, ['sql' => "UPDATE orders SET status = 'paid'"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query']);

    expect($this->project->driver_config->getDriver()->getConnection()->table('orders')->where('status', 'paid')->count())->toBe(2);
});

it('keeps the plans of a project away from other users', function () {
    $this->actingAs(User::factory()->create())
        ->postJson($this->explainUrl, ['sql' => 'SELECT * FROM orders'])
        ->assertForbidden();
});
