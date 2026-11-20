<?php

use App\Services\Cloning\Engine\Data\Schema\ColumnDefaultData;

it('reads the default value each database reports', function (?string $raw, array $options, ?array $expected) {
    $default = ColumnDefaultData::parse($raw, ...$options);

    expect($default === null ? null : [$default->kind, $default->value])->toBe($expected);
})->with([
    'MySQL value, unquoted' => ['l\'article', ['quotedLiterals' => false], [ColumnDefaultData::VALUE, "l'article"]],
    'MySQL current time' => ['CURRENT_TIMESTAMP(3)', ['mysqlExpression' => true, 'quotedLiterals' => false], [ColumnDefaultData::CURRENT_TIMESTAMP, '']],
    'MySQL expression' => ['uuid()', ['mysqlExpression' => true, 'quotedLiterals' => false], [ColumnDefaultData::EXPRESSION, 'uuid()']],
    'MariaDB escaped value' => ["'C:\\\\temp l''x'", ['backslashEscapes' => true], [ColumnDefaultData::VALUE, "C:\\temp l'x"]],
    'PostgreSQL cast value' => ["'l''article'::character varying", [], [ColumnDefaultData::VALUE, "l'article"]],
    'PostgreSQL now' => ['now()', [], [ColumnDefaultData::CURRENT_TIMESTAMP, '']],
    'PostgreSQL boolean' => ['true', [], [ColumnDefaultData::BOOLEAN, '1']],
    'PostgreSQL sequence' => ["nextval('posts_id_seq'::regclass)", [], [ColumnDefaultData::EXPRESSION, "nextval('posts_id_seq'::regclass)"]],
    'SQLite number' => ['-5', [], [ColumnDefaultData::VALUE, '-5']],
    'no default' => ['NULL', [], null],
]);
