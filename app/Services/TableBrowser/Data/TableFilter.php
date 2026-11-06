<?php

declare(strict_types=1);

namespace App\Services\TableBrowser\Data;

use App\Services\TableBrowser\FilterOperator;

/**
 * A condition on one column of the table, compared with a value given by the user.
 */
final readonly class TableFilter
{
    public function __construct(
        public string $column,
        public FilterOperator $operator,
        public mixed $value = null,
    ) {}

    /**
     * @param  array{column: string, operator: string, value?: mixed}  $filter
     */
    public static function fromArray(array $filter): self
    {
        return new self($filter['column'], FilterOperator::from($filter['operator']), $filter['value'] ?? null);
    }

    /**
     * @return array{column: string, operator: string, value: mixed}
     */
    public function toArray(): array
    {
        return ['column' => $this->column, 'operator' => $this->operator->value, 'value' => $this->value];
    }
}
