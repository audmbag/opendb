<?php

use App\Ai\Tools\Database\ShowChart;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('show-chart-').'.sqlite';
    $this->driver = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...))->driver_config->getDriver();
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('describes the chart to display from the columns of the query', function () {
    $result = (string) new ShowChart($this->driver)->handle(new Request([
        'query' => 'SELECT status, SUM(total) AS revenue FROM orders GROUP BY status ORDER BY status',
        'bindings' => [],
        'chartType' => 'bar',
        'xColumn' => 'status',
        'yColumns' => ['revenue'],
    ]));

    expect(json_decode($result, true))->toBe([
        'success' => true,
        'title' => null,
        'chartType' => 'bar',
        'xColumn' => 'status',
        'yColumns' => ['revenue'],
        'stacked' => false,
        'totalRows' => 2,
        'truncated' => false,
    ]);
});

it('names the available columns when an axis is missing from the result', function () {
    $result = (string) new ShowChart($this->driver)->handle(new Request([
        'query' => 'SELECT status, SUM(total) AS revenue FROM orders GROUP BY status',
        'bindings' => [],
        'chartType' => 'line',
        'xColumn' => 'month',
        'yColumns' => ['revenue'],
    ]));

    expect(json_decode($result, true))->toBe([
        'success' => false,
        'error' => 'Columns missing from the query result: month. Available columns: status, revenue.',
    ]);
});
