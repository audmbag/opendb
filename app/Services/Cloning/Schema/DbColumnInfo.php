<?php

declare(strict_types=1);

namespace App\Services\Cloning\Schema;

final class DbColumnInfo
{
    public const int SAFE_INTEGER = 9007199254740991;

    private const array INTEGER_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'serial', 'bigserial', 'smallserial'];

    public function __construct(
        public readonly string $type,
        public readonly bool $nullable,
        public readonly bool $primaryKey,
        public readonly bool $compositePrimaryKey,
        public readonly bool $foreignKey,
        /** Colonne seule couverte par un index unique (hors clé primaire). */
        public readonly bool $unique,
    ) {}

    public function category(): string
    {
        $t = strtolower(trim($this->type));

        return match (true) {
            (bool) preg_match('/^(tinyint\(1\)|bool|boolean|bit\(1\))/', $t) => 'boolean',
            (bool) preg_match('/^(json|jsonb)(?![a-z])/', $t) => 'json',
            (bool) preg_match('/^(tinyblob|mediumblob|longblob|blob|binary|varbinary|bytea)(?![a-z])/', $t) => 'binary',
            (bool) preg_match('/^(tinytext|mediumtext|longtext|text|char|varchar|nchar|nvarchar|character|citext|uuid|ulid)(?![a-z])/', $t) => 'string',
            (bool) preg_match('/^(tinyint|smallint|mediumint|bigint|int|integer|decimal|numeric|float|double|real|serial|bigserial|smallserial|money)(?![a-z])/', $t) => 'numeric',
            (bool) preg_match('/^(datetime|timestamptz|timestamp|date|timetz|time|year)(?![a-z])/', $t) => 'datetime',
            default => 'other',
        };
    }

    public function isNumeric(): bool
    {
        return $this->category() === 'numeric';
    }

    public function baseType(): string
    {
        return preg_match('/^[a-z]+/', strtolower(trim($this->type)), $m) === 1 ? $m[0] : '';
    }

    public function isUnsigned(): bool
    {
        return str_contains(strtolower($this->type), 'unsigned');
    }

    public function isInteger(): bool
    {
        return $this->category() === 'numeric' && in_array($this->baseType(), self::INTEGER_TYPES, true);
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    public function integerRange(): ?array
    {
        $bits = match ($this->baseType()) {
            'tinyint' => 8,
            'smallint', 'smallserial' => 16,
            'mediumint' => 24,
            'int', 'integer', 'serial' => 32,
            'bigint', 'bigserial' => 64,
            default => null,
        };

        if ($bits === null || $this->category() !== 'numeric') {
            return null;
        }

        $unsigned = $this->isUnsigned();

        if ($bits === 64) {
            $min = $unsigned ? 0 : PHP_INT_MIN;
            $max = PHP_INT_MAX;
        } else {
            $min = $unsigned ? 0 : -(1 << ($bits - 1));
            $max = $unsigned ? (1 << $bits) - 1 : (1 << ($bits - 1)) - 1;
        }

        return [max($min, -self::SAFE_INTEGER), min($max, self::SAFE_INTEGER)];
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    public function decimalPrecision(): ?array
    {
        if ($this->category() !== 'numeric' || $this->isInteger()) {
            return null;
        }

        return preg_match('/\(\s*(\d+)\s*,\s*(\d+)\s*\)/', $this->type, $m) === 1
            ? [(int) $m[1], (int) $m[2]]
            : null;
    }

    public function maxLength(): ?int
    {
        if ($this->category() !== 'string') {
            return null;
        }

        return match ($this->baseType()) {
            'tinytext' => 255,
            'text' => 65535,
            'mediumtext' => 16777215,
            'longtext' => 4294967295,
            'uuid' => 36,
            'ulid' => 26,
            'char', 'varchar', 'nchar', 'nvarchar', 'character' => preg_match('/\(\s*(\d+)\s*\)/', $this->type, $m) === 1
                ? (int) $m[1]
                : null,
            default => null,
        };
    }
}
