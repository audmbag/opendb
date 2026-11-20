<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

/**
 * Résultat de l'insertion d'un lot : quand l'insertion groupée échoue, les lignes sont reprises une à une.
 */
final readonly class ChunkWriteResult
{
    /**
     * @param  ?string  $bulkError  Erreur de l'insertion groupée, null si elle a réussi.
     * @param  array<int, string>  $rowErrors  Erreur de chaque ligne refusée, par rang dans le lot.
     */
    public function __construct(
        public int $inserted,
        public ?string $bulkError = null,
        public array $rowErrors = [],
    ) {}
}
