<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\SqlDump\Dialects;

use App\Services\Cloning\Engine\Data\Schema\ColumnSchemaData;
use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;

class MySqlDumpDialect extends AbstractDumpDialect
{
    protected function dialectDisplayName(): string
    {
        return 'MySQL';
    }

    public function quoteIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    protected function qualifiedTable(string $table): string
    {
        return $this->quoteIdentifier($table);
    }

    public function preamble(bool $disableFkChecks): string
    {
        return $disableFkChecks ? "SET FOREIGN_KEY_CHECKS = 0;\n\n" : '';
    }

    public function postamble(bool $disableFkChecks, array $tables): string
    {
        return $disableFkChecks ? "\nSET FOREIGN_KEY_CHECKS = 1;\n" : '';
    }

    protected function binaryLiteral(string $hex): string
    {
        return '0x'.$hex;
    }

    protected function engine(): DatabaseConnectionType
    {
        return DatabaseConnectionType::Mysql;
    }

    protected function mapColumnType(ColumnSchemaData $column, bool $inKey = false, ?DatabaseConnectionType $source = null): string
    {
        $unsigned = $column->unsigned;
        $fraction = $this->fractionalSeconds($column, $source);
        $withFraction = fn (string $type): string => $fraction > 0 ? "{$type}({$fraction})" : $type;

        return match ($this->kind($column, $source)) {
            'boolean' => 'TINYINT(1)',
            'tinyint' => $unsigned ? 'TINYINT UNSIGNED' : 'TINYINT',
            'smallint' => $unsigned ? 'SMALLINT UNSIGNED' : 'SMALLINT',
            'int' => $unsigned ? 'INT UNSIGNED' : 'INT',
            'bigint' => $unsigned ? 'BIGINT UNSIGNED' : 'BIGINT',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'decimal' => 'DECIMAL('.$this->decimalSpec($column).')',
            // Sans longueur (SQLite, PostgreSQL), une chaîne n'a pas de limite : seule une clé l'oblige à en avoir une.
            'varchar', 'char', 'enum' => match (true) {
                $column->length !== null && $column->length > 0 => $this->kind($column, $source) === 'char' && $column->length <= 255
                    ? 'CHAR('.$column->length.')'
                    : $this->mysqlVarchar($column->length),
                $inKey => 'VARCHAR(255)',
                default => 'TEXT',
            },
            'text' => $inKey ? 'VARCHAR(255)' : 'LONGTEXT',
            'date' => 'DATE',
            'datetime', 'timestamp', 'timestamptz' => $withFraction('DATETIME'),
            'time' => $withFraction('TIME'),
            'year' => 'YEAR',
            'json' => 'JSON',
            'blob' => $inKey ? 'VARBINARY(255)' : 'LONGBLOB',
            'uuid' => 'CHAR(36)',
            default => $inKey ? 'VARCHAR(255)' : 'LONGTEXT',
        };
    }

    protected function identityType(string $type): ?string
    {
        return preg_match('/^(tiny|small|medium|big)?int(eger)?\b/i', $type) === 1 ? $type.' AUTO_INCREMENT' : null;
    }

    /**
     * MySQL n'indexe un texte ou un binaire sans longueur que sur ses premiers caractères.
     */
    protected function indexedColumn(ColumnSchemaData $column, string $type): string
    {
        return $this->quoteIdentifier($column->name).(preg_match('/(text|blob)$/i', $type) === 1 ? '(191)' : '');
    }

    /**
     * Sans NO_BACKSLASH_ESCAPES, MySQL lit la barre oblique inverse comme un échappement dans une chaîne.
     */
    protected function stringLiteral(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
    }

    /**
     * Un texte, un binaire ou un JSON ne prend une valeur par défaut que sous forme d'expression (MySQL 8.0.13).
     */
    protected function defaultValue(string $value, string $type): string
    {
        return preg_match('/(text|blob|json|geometry)$/i', $type) === 1 ? '('.$this->stringLiteral($value).')' : $this->stringLiteral($value);
    }

    /**
     * CURRENT_TIMESTAMP doit porter la précision de la colonne, et ne vaut que pour une date avec heure.
     */
    protected function defaultCurrentTimestamp(string $type): ?string
    {
        if (preg_match('/^(datetime|timestamp)\b(?:\((\d)\))?/i', $type, $matches) !== 1) {
            return null;
        }

        return 'CURRENT_TIMESTAMP'.(isset($matches[2]) ? '('.$matches[2].')' : '');
    }

    protected function defaultExpression(string $expression): string
    {
        return str_starts_with($expression, '(') ? $expression : '('.$expression.')';
    }

    /**
     * InnoDB refuse SET DEFAULT.
     */
    protected function foreignKeyRule(string $rule): string
    {
        return $rule === 'SET DEFAULT' ? 'NO ACTION' : $rule;
    }

    private function mysqlVarchar(int $length): string
    {
        return $length > 16383 ? 'LONGTEXT' : 'VARCHAR('.$length.')';
    }
}
