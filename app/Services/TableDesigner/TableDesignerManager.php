<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use Closure;

/**
 * Passerelle entre l'application et la conception des tables : créer une table, modifier sa structure, la renommer,
 * la vider, la copier ou la supprimer.
 *
 * @phpstan-import-type TableDesign from TableDesignReader
 */
final readonly class TableDesignerManager
{
    public function __construct(
        private TableDesignReader $reader,
        private TableBlueprintBuilder $builder,
        private TableDesignApplier $applier,
        private TableOperations $operations,
    ) {}

    /**
     * The structure of the table, as the designer edits it.
     *
     * @return TableDesign
     *
     * @throws TableNotFoundException
     */
    public function design(DriverContract $driver, string $table): array
    {
        return $this->reader->read($driver, $table);
    }

    /**
     * The statements that would give the table the design, or create it when `$table` is null.
     *
     * @param  TableDesign  $design
     * @return list<string>
     *
     * @throws TableNotFoundException|TableDesignFailedException
     */
    public function preview(DriverContract $driver, ?string $table, array $design): array
    {
        return $this->applier->preview($driver, $this->phases($driver, $table, $design));
    }

    /**
     * @param  TableDesign  $design
     * @return list<string> the statements that ran
     *
     * @throws TableNotFoundException|TableDesignFailedException
     */
    public function apply(DriverContract $driver, ?string $table, array $design): array
    {
        return $this->applier->apply($driver, $this->phases($driver, $table, $design));
    }

    /**
     * @throws TableNotFoundException|TableDesignFailedException
     */
    public function rename(DriverContract $driver, string $table, string $name): void
    {
        $this->operations->rename($driver, $table, $name);
    }

    /**
     * @throws TableNotFoundException|TableDesignFailedException
     */
    public function truncate(DriverContract $driver, string $table): int
    {
        return $this->operations->truncate($driver, $table);
    }

    /**
     * @throws TableNotFoundException|TableDesignFailedException
     */
    public function drop(DriverContract $driver, string $table): void
    {
        $this->operations->drop($driver, $table);
    }

    /**
     * @throws TableNotFoundException|TableDesignFailedException
     */
    public function duplicate(DriverContract $driver, string $table, string $name, bool $withRows): void
    {
        $this->operations->duplicate($driver, $table, $name, $withRows);
    }

    /**
     * @param  TableDesign  $design
     * @return list<Closure(): list<string>>
     */
    private function phases(DriverContract $driver, ?string $table, array $design): array
    {
        return $table === null
            ? $this->builder->create($driver->getConnection(), $design)
            : $this->builder->alter($driver->getConnection(), $this->reader->read($driver, $table), $design);
    }
}
