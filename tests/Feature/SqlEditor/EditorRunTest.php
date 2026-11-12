<?php

use App\Models\QueryHistoryEntry;
use App\Models\User;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sql-editor-run-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
    $this->editorUrl = "/{$this->project->id}/sql-editor";
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * @param  array<string, mixed>  $data
 */
function runInEditor(array $data)
{
    return test()->actingAs(test()->user)->postJson(test()->editorUrl.'/run', $data);
}

function paidOrders(): int
{
    return test()->project->driver_config->getDriver()->getConnection()->table('orders')->where('status', 'paid')->count();
}

describe('parameters', function () {
    it('binds to each statement only the values of its own parameters', function () {
        runInEditor([
            'sql' => "SELECT name FROM customers WHERE city = :city;\nSELECT COUNT(*) AS total FROM orders WHERE total > :minimum AND status = :status;",
            'bindings' => ['city' => 'Paris', 'minimum' => '60', 'status' => 'paid'],
        ])
            ->assertOk()
            ->assertJsonPath('data.results.0.rows', [['Ada']])
            ->assertJsonPath('data.results.1.rows', [[1]])
            ->assertJsonPath('data.error', null);
    });

    it('binds NULL, and leaves the parameters written in text values or comments alone', function () {
        runInEditor([
            'sql' => "SELECT name, ':city' AS label FROM customers WHERE city IS :city -- :ignored\nORDER BY id",
            'bindings' => ['city' => null],
        ])
            ->assertOk()
            ->assertJsonPath('data.results.0.rows', [['Alan', ':city']]);
    });

    it('reports a parameter without value on its statement', function () {
        runInEditor(['sql' => 'SELECT 1; SELECT * FROM customers WHERE id = :id;'])
            ->assertOk()
            ->assertJsonPath('data.results.0.rows', [[1]])
            ->assertJsonPath('data.error.index', 2)
            ->assertJsonPath('data.error.message', 'Aucune valeur pour le paramètre :id.');
    });

    it('explains and exports a query with its parameters', function () {
        $this->actingAs($this->user)
            ->postJson("{$this->editorUrl}/explain", ['sql' => 'SELECT * FROM orders WHERE status = :status', 'bindings' => ['status' => 'paid']])
            ->assertOk();

        $response = $this->post("{$this->editorUrl}/export", ['sql' => 'SELECT name FROM customers WHERE city = :city', 'format' => 'csv', 'bindings' => ['city' => 'Lyon']]);

        expect($response->streamedContent())->toBe("name\nGrace\n");
    });
});

describe('transactions', function () {
    it('keeps every statement of an all-or-nothing run that succeeds', function () {
        runInEditor(['sql' => "UPDATE orders SET status = 'paid';", 'mode' => 'atomic'])
            ->assertOk()
            ->assertJsonPath('data.transaction', 'committed');

        expect(paidOrders())->toBe(3);
    });

    it('undoes the statements before the one that fails in an all-or-nothing run', function () {
        runInEditor(['sql' => "UPDATE orders SET status = 'paid'; SELECT * FROM invoices;", 'mode' => 'atomic'])
            ->assertOk()
            ->assertJsonPath('data.results.0.affected', 3)
            ->assertJsonPath('data.error.index', 2)
            ->assertJsonPath('data.transaction', 'rolled_back');

        expect(paidOrders())->toBe(2);
    });

    it('shows the effect of a dry run then undoes it, structure included', function () {
        runInEditor(['sql' => 'DELETE FROM orders; CREATE TABLE notes (id INTEGER); SELECT COUNT(*) FROM orders;', 'mode' => 'dry_run'])
            ->assertOk()
            ->assertJsonPath('data.results.0.affected', 3)
            ->assertJsonPath('data.results.2.rows', [[0]])
            ->assertJsonPath('data.transaction', 'rolled_back');

        $schema = $this->project->driver_config->getDriver()->getConnection()->getSchemaBuilder();

        expect($schema->hasTable('notes'))->toBeFalse()
            ->and(paidOrders())->toBe(2);
    });

    it('keeps each statement as it runs in auto-commit', function () {
        runInEditor(['sql' => "UPDATE orders SET status = 'paid'; SELECT * FROM invoices;"])
            ->assertOk()
            ->assertJsonPath('data.transaction', null);

        expect(paidOrders())->toBe(3);
    });

    it('rejects an unknown mode', function () {
        runInEditor(['sql' => 'SELECT 1', 'mode' => 'later'])->assertJsonValidationErrors(['mode']);
    });
});

describe('read-only', function () {
    it('runs only the statements that read, and keeps nothing', function () {
        $this->project->driver_config->update(['read_only' => true]);

        runInEditor(['sql' => "SELECT COUNT(*) FROM orders; UPDATE orders SET status = 'paid';"])
            ->assertOk()
            ->assertJsonPath('data.readOnly', true)
            ->assertJsonPath('data.results.0.rows', [[3]])
            ->assertJsonPath('data.results.0.source', null)
            ->assertJsonPath('data.error.index', 2)
            ->assertJsonPath('data.error.message', 'Lecture seule : seules les lectures (SELECT, WITH, EXPLAIN, SHOW, DESCRIBE) sont exécutées dans ce projet.');

        expect(paidOrders())->toBe(2);
    });

    it('is switched by the owner of the project and shown by the page', function () {
        $this->actingAs($this->user)
            ->putJson("{$this->editorUrl}/read-only", ['readOnly' => true])
            ->assertOk()
            ->assertJsonPath('data.readOnly', true);

        expect($this->project->driver_config->refresh()->read_only)->toBeTrue();

        $this->get($this->editorUrl)->assertInertia(fn ($page) => $page->where('readOnly', true));
    });

    it('cannot be switched by another user', function () {
        $this->actingAs(User::factory()->create())
            ->putJson("{$this->editorUrl}/read-only", ['readOnly' => true])
            ->assertForbidden();

        expect($this->project->driver_config->refresh()->read_only)->toBeFalse();
    });
});

describe('editable results', function () {
    it('names the table whose cells a result shows as they are', function (string $sql) {
        runInEditor(['sql' => $sql])
            ->assertOk()
            ->assertJsonPath('data.results.0.source', ['table' => 'orders', 'primaryKey' => ['id']]);
    })->with([
        'every column' => 'SELECT * FROM orders',
        'some columns with a filter' => 'SELECT id, status FROM orders WHERE total > 60 ORDER BY id LIMIT 10',
        'qualified by an alias' => 'SELECT o.id, o.total FROM orders o',
        'quoted' => 'SELECT "id", "status" FROM "orders"',
    ]);

    it('does not let a result be changed when its rows are not the rows of a table', function (string $sql) {
        runInEditor(['sql' => $sql])
            ->assertOk()
            ->assertJsonPath('data.results.0.source', null);
    })->with([
        'join' => 'SELECT orders.* FROM orders JOIN customers ON customers.id = orders.customer_id',
        'several tables' => 'SELECT orders.id FROM orders, customers',
        'alias' => 'SELECT id, total AS amount FROM orders',
        'expression' => 'SELECT id, total * 2 FROM orders',
        'aggregate' => 'SELECT status, COUNT(*) FROM orders GROUP BY status',
        'distinct' => 'SELECT DISTINCT id, status FROM orders',
        'without primary key' => 'SELECT status FROM orders',
        'subquery' => 'SELECT * FROM (SELECT * FROM orders) AS sub',
        'union' => 'SELECT id FROM orders UNION SELECT id FROM customers',
    ]);
});

describe('rows beyond the first thousand', function () {
    it('returns the next page of a SELECT, with its parameters', function () {
        $connection = $this->project->driver_config->getDriver()->getConnection();
        $connection->table('customers')->insert(array_map(fn (int $index): array => ['name' => "client {$index}", 'city' => 'Nantes'], range(1, 1200)));

        $this->actingAs($this->user)
            ->postJson("{$this->editorUrl}/rows", ['sql' => 'SELECT id, name FROM customers WHERE city = :city ORDER BY id', 'bindings' => ['city' => 'Nantes'], 'offset' => 1000])
            ->assertOk()
            ->assertJsonCount(200, 'data.rows')
            ->assertJsonPath('data.rows.0', [1004, 'client 1001']);
    });

    it('refuses a statement that is not a SELECT, and changes nothing', function () {
        $this->actingAs($this->user)
            ->postJson("{$this->editorUrl}/rows", ['sql' => "UPDATE orders SET status = 'paid'", 'offset' => 1000])
            ->assertUnprocessable();

        expect(paidOrders())->toBe(2);
    });
});

describe('cancel', function () {
    it('says that SQLite cannot interrupt a query', function () {
        $this->actingAs($this->user)
            ->postJson("{$this->editorUrl}/cancel", ['runId' => fake()->uuid()])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cette base ne permet pas d\'interrompre une requête en cours.');
    });

    it('requires the id of the run', function () {
        $this->actingAs($this->user)->postJson("{$this->editorUrl}/cancel", ['runId' => 'x'])->assertJsonValidationErrors(['runId']);
    });

    it('forgets the run once it is over', function () {
        $runId = fake()->uuid();

        runInEditor(['sql' => 'SELECT 1', 'runId' => $runId])->assertOk();

        expect(cache()->has("sql-editor:run:{$this->project->id}:{$runId}"))->toBeFalse();
    });
});

it('keeps the runs of every mode in the history', function () {
    runInEditor(['sql' => 'DELETE FROM orders', 'mode' => 'dry_run'])->assertOk();

    expect(QueryHistoryEntry::sole()->sql)->toBe('DELETE FROM orders');
});

it('keeps the new actions of the editor away from other users', function (string $method, string $uri) {
    $this->actingAs(User::factory()->create())->json($method, $this->editorUrl.$uri, ['sql' => 'SELECT 1', 'offset' => 0, 'runId' => fake()->uuid()])->assertForbidden();
})->with([
    'rows' => ['POST', '/rows'],
    'cancel' => ['POST', '/cancel'],
]);
