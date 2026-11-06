<?php

declare(strict_types=1);

namespace App\Services\TableBrowser;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * The comparisons a filter of the table browser may make, the only ones turned into SQL.
 */
enum FilterOperator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEquals = '>=';
    case LessThan = '<';
    case LessThanOrEquals = '<=';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case In = 'in';
    case IsNull = 'is_null';
    case IsNotNull = 'is_not_null';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function needsValue(): bool
    {
        return ! in_array($this, [self::IsNull, self::IsNotNull], true);
    }

    /**
     * Add the comparison of the quoted column with the value to the query, joined to the previous ones by `and` or `or`.
     */
    public function apply(Builder $query, Expression $column, mixed $value, string $boolean): void
    {
        match ($this) {
            self::Equals, self::NotEquals, self::GreaterThan, self::GreaterThanOrEquals, self::LessThan, self::LessThanOrEquals => $query->where($column, $this->value, $value, $boolean),
            self::Contains => $query->whereLike($this->asText($query, $column), "%{$value}%", false, $boolean),
            self::NotContains => $query->whereLike($this->asText($query, $column), "%{$value}%", false, $boolean, true),
            self::StartsWith => $query->whereLike($this->asText($query, $column), "{$value}%", false, $boolean),
            self::EndsWith => $query->whereLike($this->asText($query, $column), "%{$value}", false, $boolean),
            self::In => $query->whereIn($column, self::listOf($value), $boolean),
            self::IsNull => $query->whereNull($column, $boolean),
            self::IsNotNull => $query->whereNotNull($column, $boolean),
        };
    }

    /**
     * The values of an `in` filter, given as a list or as a comma separated string.
     *
     * @return list<string>
     */
    public static function listOf(mixed $value): array
    {
        return (is_array($value) ? $value : explode(',', (string) $value))
            |> (fn (array $values): array => array_map(fn (mixed $item): string => trim((string) $item), $values))
            |> (fn (array $values): array => array_values(array_filter($values, fn (string $item): bool => $item !== '')));
    }

    /**
     * PostgreSQL compares only text with LIKE: other columns are cast, so a number or a date can be searched too.
     */
    protected function asText(Builder $query, Expression $column): Expression
    {
        return $query->getConnection()->getDriverName() === 'pgsql'
            ? new Expression("CAST({$column->getValue($query->getGrammar())} AS TEXT)")
            : $column;
    }
}
