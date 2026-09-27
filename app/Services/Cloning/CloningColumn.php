<?php

namespace App\Services\Cloning;

final class CloningColumn
{
    public const array AVAILABLE_STRATEGIES = ['keep', 'fake', 'hash', 'mask', 'null', 'static'];

    private function __construct(
        public readonly string $name,
        public readonly CloningTransformation $transformation,
        public readonly ?string $type,
        public readonly ?PiiMatcherMatch $detectedMatcher,
    ) {}

    public static function fromRaw(
        string $name,
        array $data,
        string $tableName,
        DbSchemaCatalog $schema,
        PiiMatcherCatalog $matchers,
    ): self {
        return new self(
            name: $name,
            transformation: CloningTransformation::fromRaw($data),
            type: $schema->getColumnType($tableName, $name),
            detectedMatcher: $matchers->match($name),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            transformation: CloningTransformation::fromArray($data),
            type: $data['type'] ?? null,
            detectedMatcher: null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            ...$this->transformation->toArray(),
            'type' => $this->type,
            'detectedMatcher' => $this->detectedMatcher?->toArray(),
            //            'options' => self::AVAILABLE_STRATEGIES,
        ];
    }

    public function toRaw(): array
    {
        return $this->transformation->toRaw();
    }
}
