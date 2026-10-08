<?php

declare(strict_types=1);

namespace App\Services\Cloning\Config;

use App\Services\Cloning\Faker\CloningFakerCatalog;
use App\Services\Cloning\Pii\PiiMatcherCatalog;
use App\Services\Cloning\Pii\PiiMatcherMatch;
use App\Services\Cloning\Schema\DbColumnInfo;
use App\Services\Cloning\Schema\DbSchemaCatalog;
use App\Services\Cloning\Strategy\CloningStrategyResolver;
use App\Services\Cloning\Strategy\CloningTransformation;

final class CloningColumn
{
    public const array AVAILABLE_STRATEGIES = ['keep', 'fake', 'hash', 'mask', 'null', 'static'];

    public readonly ?string $catalogKey;

    private function __construct(
        public readonly string $name,
        public readonly CloningTransformation $transformation,
        public readonly ?string $type,
        public readonly ?PiiMatcherMatch $detectedMatcher,
        public readonly array $options,
        public readonly array $catalog,
        public readonly ?array $suggestedFakerMethod,
        private readonly ?DbColumnInfo $info = null,
    ) {
        $this->catalogKey = CloningFakerCatalog::catalogKey($catalog);
    }

    public static function fromRaw(
        string $name,
        array $data,
        string $tableName,
        DbSchemaCatalog $schema,
        PiiMatcherCatalog $matchers,
    ): self {
        $info = $schema->getColumn($tableName, $name);
        $transformation = CloningStrategyResolver::adapt(CloningTransformation::fromRaw($data), $info);
        $detected = $matchers->match($name);
        $options = CloningStrategyResolver::options($info, $transformation->strategy);
        $canFake = in_array('fake', $options, true);

        return new self(
            name: $name,
            transformation: $transformation,
            type: $info?->type,
            detectedMatcher: $detected,
            options: $options,
            catalog: $canFake ? CloningFakerCatalog::catalogFor($info) : [],
            suggestedFakerMethod: $canFake ? CloningFakerCatalog::suggestionFor($info, $detected) : null,
            info: $info,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            ...$this->transformation->toArray(),
            'type' => $this->type,
            'detectedMatcher' => $this->detectedMatcher?->toArray($this->info),
            'options' => $this->options,
            'fakerCatalogKey' => $this->catalogKey,
            'suggestedFakerMethod' => $this->suggestedFakerMethod,
        ];
    }
}
