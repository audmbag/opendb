<?php

namespace App\Console\Commands\Chat\Turn;

use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\PendingApproval;

/**
 * How an answer of the agent ended.
 */
final readonly class TurnResult
{
    /**
     * @param  Collection<int, PendingApproval>  $pendingApprovals  the tool calls the agent waits a decision for
     */
    public function __construct(public Collection $pendingApprovals = new Collection) {}
}
