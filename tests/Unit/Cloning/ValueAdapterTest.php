<?php

use App\Services\Cloning\Engine\Cloning\Transfer\ValueAdapter;
use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Data\Schema\TableSchemaData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;

function adaptedRow(string $type, mixed $value, DatabaseConnectionType $source, DatabaseConnectionType $target): mixed
{
    $table = new TableSchemaData('t', [new ColumnSchemaData('value', $type, true, null, false)], []);

    return ValueAdapter::for($table, $source, $target)->apply([['value' => $value]])[0]['value'];
}

it('reads the bytea stream of PostgreSQL', function () {
    $stream = fopen('php://memory', 'r+b');
    fwrite($stream, "\x00\xffbinaire");
    rewind($stream);

    expect(adaptedRow('bytea', $stream, DatabaseConnectionType::PostgreSQL, DatabaseConnectionType::Mysql))->toBe("\x00\xffbinaire");
});

it('drops the UTC offset of a PostgreSQL date for the other databases only', function () {
    expect(adaptedRow('timestamp with time zone', '2024-02-29 13:45:56.789+00', DatabaseConnectionType::PostgreSQL, DatabaseConnectionType::Mysql))->toBe('2024-02-29 13:45:56.789')
        ->and(adaptedRow('timestamp with time zone', '2024-02-29 13:45:56+00', DatabaseConnectionType::PostgreSQL, DatabaseConnectionType::PostgreSQL))->toBe('2024-02-29 13:45:56+00');
});

it('turns the zero dates of MySQL, that PostgreSQL refuses, into NULL', function () {
    expect(adaptedRow('datetime', '0000-00-00 00:00:00', DatabaseConnectionType::Mysql, DatabaseConnectionType::PostgreSQL))->toBeNull()
        ->and(adaptedRow('datetime', '0000-00-00 00:00:00', DatabaseConnectionType::Mysql, DatabaseConnectionType::Sqlite))->toBe('0000-00-00 00:00:00');
});

it('lists the binary columns to send as such', function () {
    $table = new TableSchemaData('t', [new ColumnSchemaData('avatar', 'longblob', true, null, false), new ColumnSchemaData('name', 'varchar', true, null, false)], []);

    expect(ValueAdapter::binaryColumns($table))->toBe(['avatar']);
});
