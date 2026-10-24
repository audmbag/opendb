<?php

use App\Database\Drivers\MySQLDriver;

it('builds the plan tree of a join from the JSON returned by EXPLAIN', function () {
    $plan = [
        'query_block' => [
            'select_id' => 1,
            'cost_info' => ['query_cost' => '301.30'],
            'ordering_operation' => [
                'using_filesort' => true,
                'nested_loop' => [
                    ['table' => ['table_name' => 'o', 'access_type' => 'ALL', 'rows_examined_per_scan' => 1884, 'cost_info' => ['prefix_cost' => '191.40']]],
                    ['table' => ['table_name' => 'c', 'access_type' => 'eq_ref', 'key' => 'PRIMARY', 'rows_examined_per_scan' => 1, 'cost_info' => ['prefix_cost' => '301.30']]],
                ],
            ],
        ],
    ];

    $driver = new class(['driver_id' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'shop', 'username' => 'root', 'password' => '']) extends MySQLDriver
    {
        public array $plan = [];

        public function executeReadOnly(string $sql, array $bindings = []): array
        {
            return [(object) ['EXPLAIN' => json_encode($this->plan)]];
        }
    };
    $driver->plan = $plan;

    $tree = $driver->explain('SELECT * FROM orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.total');

    expect($tree[0]['label'])->toBe('Requête')
        ->and($tree[0]['children'][0]['warning'])->toBe('Tri sans index (filesort)')
        ->and(array_column($tree[0]['children'][0]['children'][0]['children'], 'label'))->toBe(['Table o', 'Table c'])
        ->and($tree[0]['children'][0]['children'][0]['children'][0]['warning'])->toBe('Parcours complet de la table');
});
