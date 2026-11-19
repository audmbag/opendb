<?php

declare(strict_types=1);

namespace App\Services\Disconnection;

use App\Models\Project;
use App\Models\User;

/**
 * Passerelle entre l'application et la déconnexion d'un projet : ce que l'utilisateur y laisse en cours s'arrête,
 * puis le tunnel SSH du projet se ferme s'il ne sert plus à personne.
 */
final readonly class DisconnectionManager
{
    public function __construct(
        private EditorRunStopper $runs,
        private AnswerStopper $answers,
        private IdleTunnelCloser $tunnel,
    ) {}

    /**
     * @param  list<string>  $runIds  the runs of the SQL editor the page still waits for
     */
    public function disconnect(Project $project, User $user, array $runIds): void
    {
        $interruptedRuns = $this->runs->stop($project, $user, $runIds);

        $this->answers->stop($project, $user);

        $this->tunnel->close($project, waitForInterruptedRuns: $interruptedRuns > 0);
    }
}
