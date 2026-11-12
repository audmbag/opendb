<?php

declare(strict_types=1);

namespace App\Services\Erd\Assistant;

/**
 * One change of structure asked by the assistant of the diagram, once read against the database: the design the
 * table will have, the statement to run as it is, or the reasons it cannot be made.
 *
 * @phpstan-import-type TableDesign from \App\Services\TableDesigner\TableDesignReader
 * @phpstan-import-type ForeignKeyDesign from \App\Services\TableDesigner\TableDesignReader
 */
final readonly class SchemaChange
{
    public const string CREATE = 'create_table';

    public const string ALTER = 'alter_table';

    public const string DROP = 'drop_table';

    public const string RAW = 'raw_sql';

    public const array KINDS = [self::CREATE, self::ALTER, self::DROP, self::RAW];

    /**
     * @param  self::CREATE|self::ALTER|self::DROP|self::RAW  $kind
     * @param  ?string  $table  the name the table has once changed, null for a statement run as it is
     * @param  ?string  $from  the name the table has in the database, for a table changed or dropped
     * @param  ?TableDesign  $design  the table as it will be
     * @param  ?array<string, mixed>  $current  the table as the database describes it, for a table changed
     * @param  list<ForeignKeyDesign>  $deferredForeignKeys  the keys added once the tables they reference are created
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $kind,
        public ?string $table,
        public ?string $from = null,
        public ?array $design = null,
        public ?array $current = null,
        public ?string $statement = null,
        public bool $destructive = false,
        public array $deferredForeignKeys = [],
        public array $errors = [],
    ) {}

    /**
     * @param  list<string>  $errors
     */
    public static function failed(string $kind, ?string $table, array $errors): self
    {
        return new self($kind, $table, errors: $errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @param  list<ForeignKeyDesign>  $deferred
     */
    public function withDeferredForeignKeys(array $deferred): self
    {
        $design = $this->design;

        if ($design !== null) {
            $design['foreignKeys'] = array_values(array_filter($design['foreignKeys'], fn (array $foreignKey): bool => ! in_array($foreignKey, $deferred, true)));
        }

        return new self($this->kind, $this->table, $this->from, $design, $this->current, $this->statement, $this->destructive, $deferred, $this->errors);
    }

    /**
     * @return array{kind: string, table: ?string, from: ?string, destructive: bool, errors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'table' => $this->table,
            'from' => $this->from,
            'destructive' => $this->destructive,
            'errors' => $this->errors,
        ];
    }
}
