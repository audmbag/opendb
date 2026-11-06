<?php

declare(strict_types=1);

namespace App\Services\QueryPlan\Exception;

use RuntimeException;

/**
 * Plan d'exécution impossible à calculer, avec le code d'erreur renvoyé à l'interface et à l'agent.
 */
class QueryPlanException extends RuntimeException
{
    public const string NOT_SELECT = 'not_select';

    public const string SYNTAX_ERROR = 'syntax_error';

    public const string UNKNOWN_TABLE = 'unknown_table';

    public const string HIDDEN_TABLE = 'hidden_table';

    public const string UNSUPPORTED_DRIVER = 'unsupported_driver';

    public const string OTHER = 'other';

    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    /**
     * L'erreur à renvoyer à l'agent et à l'interface.
     *
     * @return array{success: false, error: string, message: string}
     */
    public function toArray(): array
    {
        return ['success' => false, 'error' => $this->errorCode, 'message' => $this->getMessage()];
    }
}
