<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Exceptions;

use Exception;

/**
 * Le clonage a été arrêté à la demande de l'utilisateur, ou parce que la page qui le suivait a été fermée.
 * La base cible garde les tables déjà copiées.
 */
final class CloningCancelledException extends Exception
{
    public function __construct()
    {
        parent::__construct('The cloning was cancelled.');
    }
}
