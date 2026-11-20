<?php

declare(strict_types=1);

namespace App\Services\Cloning\Workflow;

use App\Services\Cloning\Engine\Cloning\CloningMonitor;
use App\Services\Cloning\Engine\Enums\CloningPhase;
use Illuminate\Support\Facades\File;

/**
 * Progression d'un clonage, partagée par fichiers entre la requête qui clone et celles de la page qui le suit.
 *
 * Le moteur écrit `progress.json` ; la page le lit chaque seconde, ce qui touche `heartbeat`. Le clonage s'arrête
 * quand la page dépose `cancel` (bouton Annuler, page quittée) ou quand elle ne donne plus de nouvelles.
 */
final class CloningProgress implements CloningMonitor
{
    const string PROGRESS_FILE = 'progress.json';

    const string CANCEL_FILE = 'cancel';

    const string HEARTBEAT_FILE = 'heartbeat';

    /**
     * Sans nouvelles de la page depuis ce délai, elle a été fermée sans prévenir : le clonage s'arrête.
     * Un navigateur ne réveille qu'une fois par minute un onglet resté longtemps en arrière-plan.
     */
    const int ABANDONED_AFTER_SECONDS = 150;

    /**
     * Écart minimal entre deux écritures de la progression d'une même table.
     */
    const float WRITE_INTERVAL_SECONDS = 0.5;

    /**
     * @var array{phase: string, table: ?string, tablePosition: int, tableCount: int, tableRows: int, tableExpectedRows: ?int}
     */
    private array $state = [
        'phase' => CloningPhase::Preparing->value,
        'table' => null,
        'tablePosition' => 0,
        'tableCount' => 0,
        'tableRows' => 0,
        'tableExpectedRows' => null,
    ];

    private float $writtenAt = 0.0;

    public function __construct(private readonly string $directory) {}

    /**
     * Commence le suivi d'un clonage, en oubliant l'annulation d'un essai précédent du même clonage.
     */
    public function start(): void
    {
        File::ensureDirectoryExists($this->directory);
        File::delete($this->path(self::CANCEL_FILE));
        $this->touchHeartbeat();
        $this->write();
    }

    public function phaseStarted(CloningPhase $phase): void
    {
        $this->state['phase'] = $phase->value;
        $this->write();
    }

    public function tableStarted(string $table, int $position, int $tableCount, ?int $expectedRows): void
    {
        $this->state = [
            'phase' => CloningPhase::Tables->value,
            'table' => $table,
            'tablePosition' => $position,
            'tableCount' => $tableCount,
            'tableRows' => 0,
            'tableExpectedRows' => $expectedRows,
        ];
        $this->write();
    }

    public function rowsCopied(int $rows): void
    {
        $this->state['tableRows'] = $rows;

        if (microtime(true) - $this->writtenAt >= self::WRITE_INTERVAL_SECONDS) {
            $this->write();
        }
    }

    public function isCancelled(): bool
    {
        clearstatcache();

        if (is_file($this->path(self::CANCEL_FILE))) {
            return true;
        }

        $heartbeat = rescue(fn (): int|false => filemtime($this->path(self::HEARTBEAT_FILE)), false, false);

        return $heartbeat !== false && time() - $heartbeat > self::ABANDONED_AFTER_SECONDS;
    }

    /**
     * Progression lue par la page, qui signale du même coup qu'elle suit toujours le clonage. Null si aucun clonage
     * n'est suivi dans ce dossier.
     *
     * @return array{phase: string, table: ?string, tablePosition: int, tableCount: int, tableRows: int, tableExpectedRows: ?int, cancelling: bool}|null
     */
    public function poll(): ?array
    {
        $path = $this->path(self::PROGRESS_FILE);

        if (! is_file($path)) {
            return null;
        }

        $this->touchHeartbeat();
        $state = json_decode((string) rescue(fn (): string => File::get($path), '', false), true);

        return is_array($state) ? [...$state, 'cancelling' => is_file($this->path(self::CANCEL_FILE))] : null;
    }

    /**
     * Demande l'arrêt du clonage, qui s'arrête après le lot de lignes en cours.
     */
    public function cancel(): void
    {
        if (is_dir($this->directory)) {
            File::put($this->path(self::CANCEL_FILE), '');
        }
    }

    /**
     * Remplace le fichier d'un coup, pour que la page ne lise jamais une écriture à moitié faite. Sous Windows, le
     * remplacement échoue quand la page lit le fichier au même instant : la prochaine écriture le rattrape.
     */
    private function write(): void
    {
        $this->writtenAt = microtime(true);
        rescue(fn () => File::replace($this->path(self::PROGRESS_FILE), (string) json_encode($this->state, JSON_UNESCAPED_UNICODE)), report: false);
    }

    private function touchHeartbeat(): void
    {
        rescue(fn (): bool => touch($this->path(self::HEARTBEAT_FILE)), report: false);
    }

    private function path(string $file): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.$file;
    }
}
