<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\SqlDump\Dialects;

use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;

class SqliteDumpDialect extends AbstractDumpDialect
{
    protected function dialectDisplayName(): string
    {
        return 'SQLite';
    }

    public function quoteIdentifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    protected function qualifiedTable(string $table): string
    {
        return $this->quoteIdentifier($table);
    }

    protected function insertKeyword(): string
    {
        return 'INSERT OR REPLACE INTO';
    }

    public function preamble(bool $disableFkChecks): string
    {
        return $disableFkChecks ? "PRAGMA foreign_keys = OFF;\n\n" : '';
    }

    public function postamble(bool $disableFkChecks, array $tables): string
    {
        return $disableFkChecks ? "\nPRAGMA foreign_keys = ON;\n" : '';
    }

    protected function binaryLiteral(string $hex): string
    {
        return "X'".$hex."'";
    }

    protected function engine(): DatabaseConnectionType
    {
        return DatabaseConnectionType::Sqlite;
    }

    /**
     * Types au nom de ceux des autres bases, que SQLite range dans ses affinités (INTEGER, REAL, NUMERIC, TEXT, BLOB).
     */
    protected function mapColumnType(ColumnSchemaData $column, bool $inKey = false, ?DatabaseConnectionType $source = null): string
    {
        return match ($this->kind($column, $source)) {
            'boolean' => 'BOOLEAN',
            'tinyint', 'smallint', 'int', 'bigint', 'year' => 'INTEGER',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'decimal' => $column->precision !== null && $column->precision > 0 ? 'NUMERIC('.$this->decimalSpec($column).')' : 'NUMERIC',
            'varchar', 'enum' => $column->length !== null && $column->length > 0 ? 'VARCHAR('.$column->length.')' : 'VARCHAR',
            'char' => $column->length !== null && $column->length > 0 ? 'CHAR('.$column->length.')' : 'VARCHAR',
            'date' => 'DATE',
            'datetime', 'timestamp', 'timestamptz' => 'DATETIME',
            'time' => 'TIME',
            'blob' => 'BLOB',
            default => 'TEXT',
        };
    }

    /**
     * Seule une colonne INTEGER PRIMARY KEY devient le rowid, que SQLite génère.
     */
    protected function identityType(string $type): ?string
    {
        return preg_match('/^(tiny|small|medium|big)?int(eger)?\b/i', $type) === 1 ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : null;
    }

    protected function foreignKeysInline(): bool
    {
        return true;
    }

    /**
     * Une expression autre qu'une constante doit être entre parenthèses.
     */
    protected function defaultExpression(string $expression): string
    {
        return str_starts_with($expression, '(') ? $expression : '('.$expression.')';
    }

    protected function identityIsPrimaryKey(): bool
    {
        return true;
    }
}
