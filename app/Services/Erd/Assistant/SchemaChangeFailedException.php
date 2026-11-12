<?php

declare(strict_types=1);

namespace App\Services\Erd\Assistant;

use App\Services\TableDesigner\TableDesignFailedException;

/**
 * A change of a plan refused by the database, with the changes that ran before it and stay made, as MySQL and
 * MariaDB commit each change of structure at once.
 */
class SchemaChangeFailedException extends TableDesignFailedException
{
    /**
     * @param  list<array{kind: string, table: ?string}>  $applied
     */
    public function __construct(TableDesignFailedException $failure, public readonly array $applied)
    {
        parent::__construct($failure->statement, $failure->getMessage(), $failure);
    }
}
