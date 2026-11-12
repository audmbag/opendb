<?php

namespace App\Services\Cloning\Strategy;

final readonly class CloningTransformation
{
    public function __construct(
        public string $strategy,
        public ?string $fakerMethod = null,
        public array $fakerArguments = [],
        public mixed $value = null,
        public ?string $algorithm = null,
        public ?int $visibleChars = null,
        public ?string $maskChar = null,
        public ?bool $preserveFormat = null,
        public ?string $remappingUse = null,
        /** @var array<int, array{table:string, column:string, selfReferential:bool}> */
        public array $foreignKeys = [],
    ) {}

    public static function fromRaw(array $data): self
    {
        $remappingUse = null;
        $foreignKeys = [];

        if (($data['strategy'] ?? null) === 'remapping') {
            foreach ($data['arguments'] ?? [] as $argument) {
                if (array_key_exists('use', $argument)) {
                    $remappingUse = $argument['use'];
                }

                if (array_key_exists('foreign_keys', $argument)) {
                    foreach ($argument['foreign_keys'] as $fk) {
                        $foreignKeys[] = [
                            'table' => $fk['table'],
                            'column' => $fk['column'],
                            'selfReferential' => (bool) ($fk['self_referential'] ?? false),
                        ];
                    }
                }
            }
        }

        return new self(
            strategy: $data['strategy'] ?? 'null',
            fakerMethod: $data['faker_method'] ?? null,
            fakerArguments: $data['faker_arguments'] ?? [],
            value: $data['value'] ?? null,
            algorithm: $data['algorithm'] ?? null,
            visibleChars: $data['visible_chars'] ?? null,
            maskChar: $data['mask_char'] ?? null,
            preserveFormat: $data['preserve_format'] ?? null,
            remappingUse: $remappingUse,
            foreignKeys: $foreignKeys,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            strategy: $data['strategy'],
            fakerMethod: $data['fakerMethod'] ?? null,
            fakerArguments: $data['fakerArguments'] ?? [],
            value: $data['value'] ?? null,
            algorithm: $data['algorithm'] ?? null,
            visibleChars: $data['visibleChars'] ?? null,
            maskChar: $data['maskChar'] ?? null,
            preserveFormat: $data['preserveFormat'] ?? null,
            remappingUse: $data['remappingUse'] ?? null,
            foreignKeys: $data['foreignKeys'] ?? [],
        );
    }

    public function toArray(): array
    {
        return ['strategy' => $this->strategy, ...$this->strategyFields(camel: true)];
    }

    public function toRaw(): array
    {
        return ['strategy' => $this->strategy, ...$this->strategyFields(camel: false)];
    }

    private function strategyFields(bool $camel): array
    {
        return match ($this->strategy) {
            'keep', 'null' => [],
            'static' => ['value' => $this->value],
            'fake' => $camel
                ? ['fakerMethod' => $this->fakerMethod, 'fakerArguments' => $this->fakerArguments]
                : ['faker_method' => $this->fakerMethod, 'faker_arguments' => $this->fakerArguments],
            'hash' => ['algorithm' => $this->algorithm],
            'mask' => $camel
                ? ['visibleChars' => $this->visibleChars, 'maskChar' => $this->maskChar, 'preserveFormat' => $this->preserveFormat]
                : ['visible_chars' => $this->visibleChars, 'mask_char' => $this->maskChar, 'preserve_format' => $this->preserveFormat],
            'remapping' => $camel
                ? ['remappingUse' => $this->remappingUse, 'foreignKeys' => $this->foreignKeys]
                : ['arguments' => $this->remappingArgumentsToRaw()],
            default => throw new \InvalidArgumentException(__('cloning.errors.unknown_strategy', ['strategy' => $this->strategy])),
        };
    }

    private function remappingArgumentsToRaw(): array
    {
        $arguments = [];

        if ($this->remappingUse !== null) {
            $arguments[] = ['use' => $this->remappingUse];
        }

        $arguments[] = [
            'foreign_keys' => array_map(
                static fn (array $fk): array => [
                    'table' => $fk['table'],
                    'column' => $fk['column'],
                    'self_referential' => $fk['selfReferential'],
                ],
                $this->foreignKeys,
            ),
        ];

        return $arguments;
    }
}
