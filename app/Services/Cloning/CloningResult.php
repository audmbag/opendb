<?php

namespace App\Services\Cloning;

use DateTimeImmutable;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class CloningResult
{
    public const string STATUS_SUCCESS = 'success';

    public const string STATUS_WARNING = 'warning';

    public const string EVENT_RUN_STARTED = 'run_started';

    public const string EVENT_RUN_FINISHED = 'run_finished';

    public const string EVENT_KEY_MAPPING_CLEANUP = 'key_mapping_cleanup_completed';

    public const string EVENT_TABLE_TRANSFERRED = 'table_transferred';

    public const string EVENT_TABLE_TRANSFER_FAILED = 'table_transfer_failed';

    public const string EVENT_TABLE_SKIPPED_BY_FLAG = 'table_skipped_by_flag';

    public const string EVENT_TABLE_SKIPPED_BY_CASCADE = 'table_skipped_by_cascade';

    public const string EVENT_TABLE_SKIPPED_SCHEMA_FAILURE = 'table_skipped_schema_failure';

    public const string EVENT_TABLE_NOT_FOUND = 'table_not_found';

    public const string EVENT_ROW_SKIPPED = 'row_skipped';

    public const array MAIN_EVENTS = [
        self::EVENT_RUN_STARTED,
        self::EVENT_TABLE_TRANSFERRED,
        self::EVENT_TABLE_TRANSFER_FAILED,
        self::EVENT_RUN_FINISHED,
        self::EVENT_KEY_MAPPING_CLEANUP,
    ];

    /** Niveaux considérés comme un problème (Monolog peut aussi émettre critical/alert/emergency). */
    public const array ISSUE_LEVELS = ['warning', 'error', 'critical', 'alert', 'emergency'];

    public const array ERROR_LEVELS = ['error', 'critical', 'alert', 'emergency'];

    /** @var list<array<string, mixed>>|null */
    private ?array $entries = null;

    /** Lignes du fichier qui n'ont pas pu être décodées (numéro de ligne => contenu brut). */
    private array $malformedLines = [];

    public function __construct(
        protected string $logPath,
    ) {}

    public static function for(string $path): self
    {
        return new self($path);
    }

    public function getLogPath(): string
    {
        return $this->logPath;
    }

    public function getStatus(): string
    {
        return $this->hasIssues() ? self::STATUS_WARNING : self::STATUS_SUCCESS;
    }

    /**
     * Lignes run_started, table_transferred, table_transfer_failed, run_finished
     * et key_mapping_cleanup_completed, dans l'ordre du fichier.
     *
     * @return list<array<string, mixed>>
     */
    public function getLogs(): array
    {
        return $this->getEntriesByEvent(...self::MAIN_EVENTS);
    }

    /**
     * Toutes les lignes décodées du journal, sans filtre.
     *
     * @return list<array<string, mixed>>
     */
    public function getAllEntries(): array
    {
        return $this->entries ??= $this->parse();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getEntriesByEvent(string ...$events): array
    {
        return array_values(array_filter(
            $this->getAllEntries(),
            static fn (array $entry): bool => in_array($entry['event'] ?? null, $events, true),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getEntriesByLevel(string ...$levels): array
    {
        return array_values(array_filter(
            $this->getAllEntries(),
            static fn (array $entry): bool => in_array($entry['level'] ?? null, $levels, true),
        ));
    }

    // Problèmes

    /** Toutes les lignes de niveau warning ou plus grave. */
    public function getIssues(): array
    {
        return $this->getEntriesByLevel(...self::ISSUE_LEVELS);
    }

    public function getErrors(): array
    {
        return $this->getEntriesByLevel(...self::ERROR_LEVELS);
    }

    public function getWarnings(): array
    {
        return $this->getEntriesByLevel('warning');
    }

    public function hasIssues(): bool
    {
        return $this->getIssues() !== [];
    }

    public function hasErrors(): bool
    {
        return $this->getErrors() !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->getWarnings() !== [];
    }

    // Informations sur le run

    /** Le run est allé jusqu'au bout (run_finished présent). Faux si Clonio a planté en cours de route. */
    public function isComplete(): bool
    {
        return $this->findFirst(self::EVENT_RUN_FINISHED) !== null;
    }

    /** Valeur `success` renvoyée par Clonio dans run_finished, null si le run n'est pas terminé. */
    public function isSuccessful(): ?bool
    {
        $finished = $this->findFirst(self::EVENT_RUN_FINISHED);

        return $finished === null ? null : (bool) ($finished['success'] ?? false);
    }

    public function getSource(): ?string
    {
        return $this->findFirst(self::EVENT_RUN_STARTED)['source'] ?? null;
    }

    public function getTarget(): ?string
    {
        return $this->findFirst(self::EVENT_RUN_STARTED)['target'] ?? null;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->toDate($this->findFirst(self::EVENT_RUN_STARTED)['ts'] ?? null);
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->toDate($this->findFirst(self::EVENT_RUN_FINISHED)['ts'] ?? null);
    }

    /** Durée du run en secondes (avec millisecondes), null si début ou fin manquant. */
    public function getDurationInSeconds(): ?float
    {
        $start = $this->getStartedAt();
        $end = $this->getFinishedAt();

        if ($start === null || $end === null) {
            return null;
        }

        return round((float) $end->format('U.u') - (float) $start->format('U.u'), 3);
    }

    /** Tables transférées : [table => ['rows' => int, 'skipped' => int]]. */
    public function getTransferredTables(): array
    {
        $tables = [];

        foreach ($this->getEntriesByEvent(self::EVENT_TABLE_TRANSFERRED) as $entry) {
            $tables[(string) ($entry['table'] ?? '')] = [
                'rows' => (int) ($entry['rows'] ?? 0),
                'skipped' => (int) ($entry['skipped'] ?? 0),
            ];
        }

        return $tables;
    }

    /** Tables en échec : [table => raison]. */
    public function getFailedTables(): array
    {
        $tables = [];

        foreach ($this->getEntriesByEvent(self::EVENT_TABLE_TRANSFER_FAILED) as $entry) {
            $tables[(string) ($entry['table'] ?? '')] = $entry['reason'] ?? null;
        }

        return $tables;
    }

    /** Tables ignorées (flag, cascade, échec de schéma, introuvables) : [table => ['event' => ..., 'reason' => ...]]. */
    public function getSkippedTables(): array
    {
        $tables = [];

        $events = [
            self::EVENT_TABLE_SKIPPED_BY_FLAG,
            self::EVENT_TABLE_SKIPPED_BY_CASCADE,
            self::EVENT_TABLE_SKIPPED_SCHEMA_FAILURE,
            self::EVENT_TABLE_NOT_FOUND,
        ];

        foreach ($this->getEntriesByEvent(...$events) as $entry) {
            $tables[(string) ($entry['table'] ?? '')] = [
                'event' => $entry['event'],
                'reason' => $entry['reason'] ?? null,
            ];
        }

        return $tables;
    }

    /** Lignes rejetées par la base cible, avec la clé primaire et l'erreur SQL. */
    public function getSkippedRows(): array
    {
        return $this->getEntriesByEvent(self::EVENT_ROW_SKIPPED);
    }

    /** Total de lignes copiées : celui de run_finished s'il existe, sinon la somme par table. */
    public function getTotalRows(): int
    {
        $finished = $this->findFirst(self::EVENT_RUN_FINISHED);

        if (isset($finished['rows'])) {
            return (int) $finished['rows'];
        }

        return array_sum(array_column($this->getTransferredTables(), 'rows'));
    }

    public function getTotalSkippedRows(): int
    {
        return array_sum(array_column($this->getTransferredTables(), 'skipped'));
    }

    /** Lignes du fichier illisibles (JSON invalide), indexées par numéro de ligne. */
    public function getMalformedLines(): array
    {
        $this->getAllEntries();

        return $this->malformedLines;
    }

    /** Résumé prêt à être renvoyé en JSON (API, Inertia, Livewire...). */
    public function toArray(): array
    {
        return [
            'status' => $this->getStatus(),
            'complete' => $this->isComplete(),
            'successful' => $this->isSuccessful(),
            'source' => $this->getSource(),
            'target' => $this->getTarget(),
            'started_at' => $this->getStartedAt()?->format(DATE_ATOM),
            'finished_at' => $this->getFinishedAt()?->format(DATE_ATOM),
            'duration' => $this->getDurationInSeconds(),
            'stats' => [
                'tables_transferred' => count($this->getTransferredTables()),
                'tables_failed' => count($this->getFailedTables()),
                'tables_skipped' => count($this->getSkippedTables()),
                'rows' => $this->getTotalRows(),
                'rows_skipped' => $this->getTotalSkippedRows(),
                'warnings' => count($this->getWarnings()),
                'errors' => count($this->getErrors()),
            ],
            'logs' => $this->getLogs(),
        ];
    }

    // Interne

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(): array
    {
        if (! is_file($this->logPath) || ! is_readable($this->logPath)) {
            throw new RuntimeException(__('cloning.errors.unreadable_log_file', ['path' => $this->logPath]));
        }

        $handle = fopen($this->logPath, 'rb');
        $entries = [];
        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->malformedLines[$lineNumber] = $line;

                continue;
            }

            if (! is_array($decoded) || ! isset($decoded['event'])) {
                $this->malformedLines[$lineNumber] = $line;

                continue;
            }

            $decoded['level'] = strtolower((string) ($decoded['level'] ?? 'info'));

            if (isset($decoded['reason'])) {
                $decoded['reason'] = Str::limit(trim((string) $decoded['reason']), 400, '...'.__('cloning.errors.truncated'), true);
            }

            $entries[] = $decoded;
        }

        fclose($handle);

        return $entries;
    }

    private function findFirst(string $event): ?array
    {
        return array_find($this->getAllEntries(), fn ($entry) => ($entry['event'] ?? null) === $event);
    }

    private function toDate(mixed $ts): ?DateTimeImmutable
    {
        if (! is_string($ts) || $ts === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($ts);
        } catch (\Exception) {
            return null;
        }
    }
}
