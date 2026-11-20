<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Enums;

/**
 * Étapes d'un clonage, dans l'ordre où le moteur les parcourt.
 */
enum CloningPhase: string
{
    case Preparing = 'preparing';
    case Schema = 'schema';
    case Remapping = 'remapping';
    case Tables = 'tables';
}
