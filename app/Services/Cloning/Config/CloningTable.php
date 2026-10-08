<?php

declare(strict_types=1);

namespace App\Services\Cloning\Config;

use App\Services\Cloning\Pii\PiiMatcherCatalog;
use App\Services\Cloning\Schema\DbSchemaCatalog;

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

    public function toArray(?array &$fakerCatalogs = null): array
    {
        $columns = [];
        foreach ($this->columns as $column) {
            if ($fakerCatalogs !== null && $column->catalogKey !== null && ! isset($fakerCatalogs[$column->catalogKey])) {
                $fakerCatalogs[$column->catalogKey] = $column->catalog;
            }
            $columns[] = $column->toArray();
        }

        return [
            'name' => $this->name,
            'rowsStrategy' => $this->rowsStrategy,
            'rowsClear' => $this->rowsClear,
            'columns' => $columns,
        ];
    }
}
