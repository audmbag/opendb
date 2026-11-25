<?php

use App\Database\Drivers\BaseDriver;
use App\Database\Schema\SchemaSnapshotCache;
use App\Database\Schema\SnapshotBuilder;
use App\Models\User;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-schema-cache-').'.sqlite';
    File::put($this->path, '');

    $this->pdo = new PDO('sqlite:'.$this->path);
    $this->pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR(191) NOT NULL UNIQUE)');

    $this->driver = BaseDriver::for(['driver_id' => 'sqlite', 'database' => $this->path]);
    $this->driver->forgetSchema();
});

afterEach(function () {
    File::delete($this->path);
});

/**
 * The queries the database of the user received while the callback ran.
 */
function queriesOfUserDatabase(BaseDriver $driver, Closure $callback): int
{
    $connection = $driver->getConnection();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    $callback();

    $count = count($connection->getQueryLog());
    $connection->disableQueryLog();

    return $count;
}

it('describes a table without querying the database once the schema was read', function () {
    $this->driver->getSchema();

    $queries = queriesOfUserDatabase($this->driver, function () use (&$description): void {
        $description = app(SnapshotBuilder::class)->describe($this->driver->getConnection(), 'customers');
    });

    expect($queries)->toBe(0)
        ->and(array_column($description['columns'], 'name'))->toBe(['id', 'email']);
});

it('reads the description of a table again once its schema is forgotten', function () {
    app(SnapshotBuilder::class)->describe($this->driver->getConnection(), 'customers');
    $this->pdo->exec('ALTER TABLE customers ADD COLUMN name VARCHAR(100)');

    expect(array_column(app(SnapshotBuilder::class)->describe($this->driver->getConnection(), 'customers')['columns'], 'name'))->toBe(['id', 'email']);

    $this->driver->forgetSchema();

    expect(array_column(app(SnapshotBuilder::class)->describe($this->driver->getConnection(), 'customers')['columns'], 'name'))->toBe(['id', 'email', 'name']);
});

it('forgets the descriptions of the tables when the user clears the caches', function () {
    $user = User::factory()->create();
    $project = projectWithSqliteDatabase($user, $this->path, fn () => null);
    $driver = $project->driver_config->getDriver();

    app(SnapshotBuilder::class)->describe($driver->getConnection(), 'customers');
    $this->pdo->exec('ALTER TABLE customers ADD COLUMN name VARCHAR(100)');

    $this->actingAs($user)->deleteJson('/settings/cache')->assertSuccessful();

    expect(array_column(app(SnapshotBuilder::class)->describe($driver->getConnection(), 'customers')['columns'], 'name'))->toBe(['id', 'email', 'name']);
});

it('shares the connection between the drivers of the same database', function () {
    $other = BaseDriver::for(['driver_id' => 'sqlite', 'database' => $this->path]);

    expect($other->getConnection())->toBe($this->driver->getConnection())
        ->and(DB::connection($this->driver->connection_name))->toBe($this->driver->getConnection());
});

it('refuses to describe an unreachable database with the tables of opendb', function () {
    File::delete($this->path);

    expect(fn () => $this->driver->getSchema())->toThrow(SQLiteDatabaseDoesNotExistException::class)
        ->and(Cache::has("truss:schema:{$this->driver->connection_name}"))->toBeFalse();
});

it('reads again a schema that Truss replaced with the migrations of opendb', function () {
    Cache::put("truss:schema:{$this->driver->connection_name}", ['connection' => $this->driver->connection_name, 'fallback' => true, 'skipped_migrations' => [], 'tables' => [['name' => 'users', 'columns' => [], 'primary_key' => [], 'indexes' => [], 'foreign_keys' => []]]]);

    expect(array_column($this->driver->getSchema(), 'name'))->toBe(['customers']);
});

it('answers with an old schema at once and reads it again after the response', function () {
    $this->driver->getSchema();
    $this->pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY)');

    $this->travel(SchemaSnapshotCache::freshSeconds() + 60)->seconds();

    $queries = queriesOfUserDatabase($this->driver, function () use (&$tables): void {
        $tables = array_column($this->driver->getSchema(), 'name');
    });

    expect($queries)->toBe(0)
        ->and($tables)->toBe(['customers']);

    defer()->invoke();

    expect(array_column($this->driver->getSchema(), 'name'))->toEqualCanonicalizing(['customers', 'orders']);
});

it('reads a schema left unread longer than its stale delay while the page waits', function () {
    $this->driver->getSchema();
    $this->pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY)');

    $this->travel(SchemaSnapshotCache::lifetime() + 60)->seconds();

    expect(array_column($this->driver->getSchema(), 'name'))->toEqualCanonicalizing(['customers', 'orders']);
});
