<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Audit;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Journal JSONL d'un clonage, au même format que le `*_process.jsonl` de clonio :
 * une ligne `{"ts","level","event",...context}` par événement.
 */
final class CloningAuditLog
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $records = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        $this->record('info', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        $this->record('warning', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        $this->record('error', $event, $context);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function records(): array
    {
        return $this->records;
    }

    public function toJsonl(): string
    {
        if ($this->records === []) {
            return '';
        }

        return implode("\n", array_map(
            static fn (array $record): string => (string) json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $this->records,
        ))."\n";
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(string $level, string $event, array $context): void
    {
        $this->records[] = [
            'ts' => new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'level' => $level,
            'event' => $event,
            ...$context,
        ];
    }
}
