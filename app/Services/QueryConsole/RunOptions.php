<?php

declare(strict_types=1);

namespace App\Services\QueryConsole;

use App\Database\Contracts\DriverContract;
use Closure;

/**
 * How a script of the console runs: the values of its `:name` parameters, its transaction, whether it may only read,
 * and what is done once its connection is open, like noting its id so that the run can be cancelled.
 */
final readonly class RunOptions
{
    /**
     * @param  array<string, ?string>  $bindings  the values of the parameters, by name without the colon
     * @param  ?Closure(DriverContract): void  $beforeRun
     * @param  bool  $readOnly  only statements that read may run, and nothing they do is kept
     */
    public function __construct(
        public array $bindings = [],
        public TransactionMode $mode = TransactionMode::Auto,
        public ?Closure $beforeRun = null,
        public bool $readOnly = false,
    ) {}
}
