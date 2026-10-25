<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine;

/**
 * Options d'un clonage, équivalentes aux options `--[no-]...`, `--break-on-failure` et `--skip-tables` de `clonio cloning:run`.
 * Une option à `null` garde la valeur du fichier `.cloning.yaml`.
 */
final readonly class CloningRunOptions
{
    /**
     * @param  list<string>  $skipTables
     */
    public function __construct(
        public ?bool $disableForeignKeyChecks = null,
        public ?bool $dropExtraColumns = null,
        public ?bool $dropUnknownTables = null,
        public ?bool $enforceColumnTypes = null,
        public bool $breakOnFailure = false,
        public array $skipTables = [],
    ) {}
}
