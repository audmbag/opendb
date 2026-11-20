<?php

use App\Services\Cloning\Engine\Data\ConnectionData;
use App\Services\Cloning\Engine\Data\Schema\ColumnDefaultData;
use App\Services\Cloning\Engine\Schema\SchemaInspector;

beforeEach(function () {
    $this->database = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-inspector-').'.sqlite';
    $pdo = new PDO('sqlite:'.$this->database);
    $pdo->exec('CREATE TABLE "my authors" (id integer primary key autoincrement, code varchar(10) not null, price numeric(15,4), email varchar)');
    $pdo->exec('CREATE UNIQUE INDEX authors_email_unique ON "my authors" (email)');
    $pdo->exec('CREATE INDEX authors_lower_code ON "my authors" (lower(code))');
    $pdo->exec("CREATE TABLE post_tag (post_id integer not null references \"my authors\" on delete cascade, tag_slug varchar not null default 'l''étiquette', primary key (tag_slug, post_id))");
});

afterEach(function () {
    @unlink($this->database);
});

it('reads the lengths, the generated key, the indexes, the foreign keys and the defaults of a SQLite table', function () {
    $schema = app(SchemaInspector::class)->inspect(ConnectionData::fromArray('source', ['type' => 'sqlite', 'database' => $this->database]));
    $authors = $schema->getTable('my authors');
    $columns = collect($authors->columns)->keyBy('name');

    expect($columns['code']->type)->toBe('varchar')
        ->and($columns['code']->length)->toBe(10)
        ->and($columns['code']->declaredType)->toBe('varchar(10)')
        ->and([$columns['price']->precision, $columns['price']->scale])->toBe([15, 4])
        ->and($columns['id']->autoIncrement)->toBeTrue()
        ->and($columns['code']->autoIncrement)->toBeFalse()
        ->and(array_map(fn ($index): array => [$index->name, $index->columns, $index->unique], $authors->indexes))->toBe([['authors_email_unique', ['email'], true]])
        ->and($schema->getTable('post_tag')->primaryKey)->toBe(['tag_slug', 'post_id']);

    $postTag = $schema->getTable('post_tag');
    $constraint = $postTag->constraints[0];
    $slug = collect($postTag->columns)->keyBy('name')['tag_slug'];

    expect([$constraint->columns, $constraint->referencedTable, $constraint->referencedColumns, $constraint->onDelete])->toBe([['post_id'], 'my authors', ['id'], 'CASCADE'])
        ->and([$slug->defaultValue?->kind, $slug->defaultValue?->value])->toBe([ColumnDefaultData::VALUE, "l'étiquette"])
        ->and(array_map(fn ($key): array => [$key->columnName, $key->referencedTable, $key->referencedColumn], $postTag->foreignKeys))->toBe([['post_id', 'my authors', 'id']]);
});
