<?php

declare(strict_types=1);

namespace App\Services\TableBrowser\Data;

/**
 * A change of the user waiting to be applied to a row: an insert, an update of some of its columns, or a delete.
 */
final readonly class TableChange
{
    /**
     * @param  'insert'|'update'|'delete'  $type
     * @param  array<string, mixed>  $key  the primary key of the updated or deleted row
     * @param  array<string, mixed>  $values  the values inserted, or the new values of the updated columns
     */
    public function __construct(
        public string $type,
        public array $key = [],
        public array $values = [],
    ) {}
}
