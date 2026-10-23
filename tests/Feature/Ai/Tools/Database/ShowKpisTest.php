<?php

use App\Ai\Tools\Database\ShowKpis;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('show-kpis-').'.sqlite';
    $this->driver = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, shopDatabase(...))->driver_config->getDriver();
});

afterEach(function () {
    @unlink($this->databasePath);
});

function kpi(string $label, string $query = 'SELECT COUNT(*) FROM orders'): array
{
    return ['label' => $label, 'query' => $query, 'bindings' => [], 'format' => 'number'];
}

it('confirms the cards to display without computing their values for the agent', function () {
    $result = (string) new ShowKpis($this->driver)->handle(new Request([
        'kpis' => [kpi('Commandes'), kpi('Chiffre d\'affaires', 'SELECT SUM(total) FROM orders')],
    ]));

    expect(json_decode($result, true))->toBe([
        'success' => true,
        'labels' => ['Commandes', 'Chiffre d\'affaires'],
        'count' => 2,
    ]);
});

it('refuses more cards than the interface displays', function () {
    $result = (string) new ShowKpis($this->driver)->handle(new Request([
        'kpis' => array_map(fn (int $index) => kpi("KPI {$index}"), range(1, ShowKpis::MAX_KPIS + 1)),
    ]));

    expect(json_decode($result, true))->toMatchArray(['success' => false]);
});

it('refuses a card whose query would change the database', function () {
    $result = (string) new ShowKpis($this->driver)->handle(new Request([
        'kpis' => [kpi('Commandes'), kpi('Purge', 'DELETE FROM orders')],
    ]));

    expect(json_decode($result, true))->toMatchArray(['success' => false]);
});

it('refuses a currency card whose currency is not given', function () {
    $result = (string) new ShowKpis($this->driver)->handle(new Request([
        'kpis' => [[...kpi('Chiffre d\'affaires', 'SELECT SUM(total) FROM orders'), 'format' => 'currency']],
    ]));

    expect(json_decode($result, true))->toMatchArray(['success' => false]);
});
