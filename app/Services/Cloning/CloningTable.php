<?php

namespace App\Services\Cloning;

final readonly class CloningTable
{
    /**
     * @param  CloningColumn[]  $columns
     */
    private function __construct(
        public string $name,
        public string $rowsStrategy,
        public ?string $rowsClear,
        public array $columns,
    ) {}

    public static function fromRaw(
        string $name,
        array $data,
        DbSchemaCatalog $schema,
        PiiMatcherCatalog $matchers,
    ): self {
        $columns = [];
        foreach ($data['columns'] ?? [] as $columnName => $columnData) {
            $columns[] = CloningColumn::fromRaw($columnName, $columnData, $name, $schema, $matchers);
        }

        return new self(
            name: $name,
            rowsStrategy: $data['rows']['strategy'],
            rowsClear: $data['rows']['clear'] ?? null,
            columns: $columns,
        );
    }

    public static function fromArray(array $data): self
    {
        $columns = array_map(
            static fn (array $column): CloningColumn => CloningColumn::fromArray($column),
            $data['columns'] ?? [],
        );

        return new self(
            name: $data['name'],
            rowsStrategy: $data['rowsStrategy'],
            rowsClear: $data['rowsClear'] ?? null,
            columns: $columns,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            //            'rowsStrategy' => $this->rowsStrategy,
            //            'rowsClear' => $this->rowsClear,
            'columns' => array_map(
                static fn (CloningColumn $column): array => $column->toArray(),
                $this->columns,
            ),
        ];
    }

    public function toRaw(): array
    {
        $rows = ['strategy' => $this->rowsStrategy];
        if ($this->rowsClear !== null) {
            $rows['clear'] = $this->rowsClear;
        }

        $columns = [];
        foreach ($this->columns as $column) {
            $columns[$column->name] = $column->toRaw();
        }

        return [
            'rows' => $rows,
            'columns' => $columns,
        ];
    }
}
