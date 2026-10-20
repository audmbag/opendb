<?php

declare(strict_types=1);

namespace App\Services\Cloning\Config;

use App\Services\Cloning\CloningSourceConfig;
use App\Services\Cloning\Pii\PiiMatcherCatalog;
use App\Services\Cloning\Schema\DbSchemaCatalog;
use Illuminate\Support\Number;

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
        public ?string $skipReason = null,
        public ?string $remappingNotice = null,
    ) {}

    public static function fromRaw(
        string $name,
        array $data,
        DbSchemaCatalog $schema,
        PiiMatcherCatalog $matchers,
        ?int $keptPrimaryKeyRows = null,
    ): self {
        $columns = [];
        foreach ($data['columns'] ?? [] as $columnName => $columnData) {
            $columns[] = CloningColumn::fromRaw((string) $columnName, $columnData, $name, $schema, $matchers);
        }

        return new self(
            name: $name,
            rowsStrategy: $data['rows']['strategy'],
            rowsClear: $data['rows']['clear'] ?? null,
            columns: $columns,
            skipReason: CloningSourceConfig::skipReason($name, $data),
            remappingNotice: $keptPrimaryKeyRows === null ? null : __('cloning.remapping_notices.too_many_rows', [
                'rows' => Number::format($keptPrimaryKeyRows, locale: app()->getLocale()),
            ]),
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
            'skipReason' => $this->skipReason,
            'remappingNotice' => $this->remappingNotice,
        ];
    }
}
