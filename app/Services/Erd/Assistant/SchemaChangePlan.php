<?php

declare(strict_types=1);

namespace App\Services\Erd\Assistant;

/**
 * The changes of structure asked at once by the assistant, in the order they run: the tables created first, those
 * referenced by others before them, then the tables changed, the statements run as they are, and the tables dropped.
 */
final readonly class SchemaChangePlan
{
    /**
     * @param  list<SchemaChange>  $changes
     */
    public function __construct(public array $changes) {}

    public function isValid(): bool
    {
        return $this->changes !== [] && array_all($this->changes, fn (SchemaChange $change): bool => $change->isValid());
    }

    /**
     * Whether a change may lose data or break what relies on the structure: a table, a column, an index or a key
     * dropped, a column changed or renamed, a table renamed, or a statement the application cannot read.
     */
    public function isDestructive(): bool
    {
        return array_any($this->changes, fn (SchemaChange $change): bool => $change->destructive);
    }

    /**
     * The reasons the changes cannot be made, each one naming the change it is about.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        $errors = [];

        foreach ($this->changes as $change) {
            foreach ($change->errors as $error) {
                $errors[] = $change->table === null ? "{$change->kind}: {$error}" : "{$change->kind} {$change->table}: {$error}";
            }
        }

        return $errors;
    }

    /**
     * @return list<array{kind: string, table: ?string, from: ?string, destructive: bool, errors: list<string>}>
     */
    public function toArray(): array
    {
        return array_map(fn (SchemaChange $change): array => $change->toArray(), $this->changes);
    }
}
