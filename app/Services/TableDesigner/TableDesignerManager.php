<?php

declare(strict_types=1);

namespace App\Services\TableDesigner;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\PrivilegeException;
use App\Database\Exceptions\TableNotFoundException;
use App\Enums\PrivilegeEnum;
use Closure;
use Laravel\Ai\Enums\Lab;
use RuntimeException;

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
        private ColumnMover $mover,
        private TableDesignAssistant $assistant,
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
        $driver->assertPrivilege(PrivilegeEnum::Select, $table);

        return $this->reader->read($driver, $table);
    }

    /**
     * The statements that would give the table the design, or create it when `$table` is null.
     *
     * @param  TableDesign  $design
     * @return list<string>
     *
     * @throws TableNotFoundException|TableDesignFailedException|PrivilegeException
     */
    public function preview(DriverContract $driver, ?string $table, array $design): array
    {
        $this->assertDesignAllowed($driver, $table);

        return $this->applier->preview($driver, $this->phases($driver, $table, $design));
    }

    /**
     * @param  TableDesign  $design
     * @return list<string> the statements that ran
     *
     * @throws TableNotFoundException|TableDesignFailedException|PrivilegeException
     */
    public function apply(DriverContract $driver, ?string $table, array $design): array
    {
        $this->assertDesignAllowed($driver, $table);

        return $this->applier->applyAtomically($driver, $this->phases($driver, $table, $design));
    }

    /**
     * The statements that would move the column after another one, or first when `$after` is null.
     *
     * @return list<string>
     *
     * @throws TableNotFoundException|TableDesignFailedException|PrivilegeException
     */
    public function previewColumnMove(DriverContract $driver, string $table, string $column, ?string $after): array
    {
        $driver->assertPrivilege(PrivilegeEnum::Alter, $table);

        return $this->mover->preview($driver, $table, $column, $after);
    }

    /**
     * @return list<string> the statements that ran
     *
     * @throws TableNotFoundException|TableDesignFailedException|PrivilegeException
     */
    public function moveColumn(DriverContract $driver, string $table, string $column, ?string $after): array
    {
        $driver->assertPrivilege(PrivilegeEnum::Alter, $table);

        return $this->mover->move($driver, $table, $column, $after);
    }

    /**
     * @throws TableNotFoundException|TableDesignFailedException|PrivilegeException
     */
    public function rename(DriverContract $driver, string $table, string $name): void
    {
        $driver->assertPrivilege(PrivilegeEnum::Alter, $table);

        $this->operations->rename($driver, $table, $name);
    }

    /**
     * @throws TableNotFoundException|TableDesignFailedException|PrivilegeException
     */
    public function truncate(DriverContract $driver, string $table): int
    {
        $driver->assertPrivilege(PrivilegeEnum::Drop, $table);

        return $this->operations->truncate($driver, $table);
    }

    /**
     * @throws TableNotFoundException|TableDesignFailedException|PrivilegeException
     */
    public function drop(DriverContract $driver, string $table): void
    {
        $driver->assertPrivilege(PrivilegeEnum::Drop, $table);

        $this->operations->drop($driver, $table);
    }

    /**
     * @throws TableNotFoundException|TableDesignFailedException|PrivilegeException
     */
    public function duplicate(DriverContract $driver, string $table, string $name, bool $withRows): void
    {
        $driver->assertPrivilege(PrivilegeEnum::Create);
        $driver->assertPrivilege(PrivilegeEnum::Select, $table);

        $this->operations->duplicate($driver, $table, $name, $withRows);
    }

    /**
     * The new table the assistant proposes from a sentence of the user, to fill the form of the designer. Nothing runs.
     *
     * @param  array<string, mixed>  $form  what the form of the designer holds
     * @return array{design: ?TableDesign, explanation: string} no design when the agent explains why it proposes none
     *
     * @throws PrivilegeException|RuntimeException
     */
    public function suggest(DriverContract $driver, string $prompt, array $form, Lab|string $provider, string $model): array
    {
        $driver->assertPrivilege(PrivilegeEnum::Create);

        return $this->assistant->suggest($driver, $prompt, $form, $provider, $model);
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

    /**
     * Creating a table needs CREATE on the project, changing one ALTER on it.
     *
     * @throws PrivilegeException
     */
    private function assertDesignAllowed(DriverContract $driver, ?string $table): void
    {
        $table === null
            ? $driver->assertPrivilege(PrivilegeEnum::Create)
            : $driver->assertPrivilege(PrivilegeEnum::Alter, $table);
    }
}
