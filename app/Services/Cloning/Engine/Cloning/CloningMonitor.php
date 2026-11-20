<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

use App\Services\Cloning\Engine\Enums\CloningPhase;

/**
 * Suit un clonage en cours : le moteur lui signale son avancement et lui demande, entre deux lots de lignes,
 * s'il doit s'arrêter.
 */
interface CloningMonitor
{
    public function phaseStarted(CloningPhase $phase): void;

    /**
     * @param  int  $position  Rang de la table parmi celles à copier, à partir de 1.
     * @param  ?int  $expectedRows  Lignes à copier, null si elles n'ont pas pu être comptées.
     */
    public function tableStarted(string $table, int $position, int $tableCount, ?int $expectedRows): void;

    /**
     * @param  int  $rows  Lignes de la table en cours copiées jusqu'ici.
     */
    public function rowsCopied(int $rows): void;

    public function isCancelled(): bool;
}
