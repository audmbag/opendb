<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProjectMemory
{
    /**
     * Nombre maximal d'entrées conservées, pour que la mémoire injectée dans les instructions reste courte.
     */
    public const int MAX_ENTRIES = 100;

    public function __construct(public readonly Project $project) {}

    /**
     * Contenu de la mémoire, une entrée par ligne (chaîne vide si aucune entrée).
     */
    public function content(): string
    {
        return implode("\n", $this->entries());
    }

    /**
     * Ajoute une entrée datée à la fin de la mémoire.
     *
     * @throws RuntimeException si la mémoire est pleine
     */
    public function add(string $note): void
    {
        $this->write(function (array $entries) use ($note): array {
            if (count($entries) >= self::MAX_ENTRIES) {
                throw new RuntimeException(__('memory.errors.full', ['max' => self::MAX_ENTRIES]));
            }

            return [...$entries, $this->formatEntry($note)];
        });
    }

    /**
     * Remplace l'unique entrée contenant `$match` par une nouvelle entrée datée.
     *
     * @throws RuntimeException si aucune ou plusieurs entrées contiennent `$match`
     */
    public function replace(string $match, string $note): void
    {
        $this->write(function (array $entries) use ($match, $note): array {
            $entries[$this->findEntryIndex($entries, $match)] = $this->formatEntry($note);

            return $entries;
        });
    }

    /**
     * Supprime l'unique entrée contenant `$match`.
     *
     * @throws RuntimeException si aucune ou plusieurs entrées contiennent `$match`
     */
    public function remove(string $match): void
    {
        $this->write(function (array $entries) use ($match): array {
            unset($entries[$this->findEntryIndex($entries, $match)]);

            return $entries;
        });
    }

    /**
     * Les entrées de la mémoire, avec la date à laquelle chacune a été écrite (null pour une entrée écrite à la main dans le fichier).
     *
     * @return list<array{date: ?string, note: string}>
     */
    public function notes(): array
    {
        return array_map(function (string $entry): array {
            preg_match('/^-\s*(?:\[(\d{4}-\d{2}-\d{2})\]\s*)?(.*)$/s', $entry, $matches);

            return ['date' => ($matches[1] ?? '') ?: null, 'note' => trim($matches[2] ?? $entry)];
        }, $this->entries());
    }

    /**
     * Remplace toutes les entrées de la mémoire, dans l'ordre donné. Une entrée sans date prend celle du jour.
     *
     * @param  list<array{date?: ?string, note: string}>  $notes
     *
     * @throws RuntimeException si les entrées dépassent la taille de la mémoire
     */
    public function sync(array $notes): void
    {
        if (count($notes) > self::MAX_ENTRIES) {
            throw new RuntimeException(__('memory.errors.too_many', ['max' => self::MAX_ENTRIES]));
        }

        $this->write(fn (): array => array_map(
            fn (array $note): string => $this->formatEntry($note['note'], $note['date'] ?? null),
            $notes,
        ));
    }

    /**
     * Supprime le dossier de mémoire du projet.
     */
    public function delete(): void
    {
        $this->disk()->deleteDirectory($this->directory());
    }

    public function path(): string
    {
        return $this->directory().'/MEMORY.md';
    }

    protected function directory(): string
    {
        return "projects/{$this->project->id}";
    }

    protected function disk(): Filesystem
    {
        return Storage::disk('opendb');
    }

    /**
     * @return list<string>
     */
    protected function entries(): array
    {
        $content = $this->disk()->exists($this->path()) ? $this->disk()->get($this->path()) : '';

        return explode("\n", (string) $content)
                |> (fn ($x) => array_filter($x, fn (string $line) => trim($line) !== ''))
                |> array_values(...);
    }

    /**
     * Applique une modification aux entrées sous verrou, plusieurs conversations du même projet pouvant écrire en même temps.
     *
     * @param  callable(list<string>): array<int, string>  $callback
     */
    protected function write(callable $callback): void
    {
        Cache::lock("project-memory:{$this->project->id}", 10)->block(5, function () use ($callback) {
            $entries = $callback($this->entries());

            $this->disk()->put($this->path(), $entries === [] ? '' : implode("\n", $entries)."\n");
        });
    }

    /**
     * @param  list<string>  $entries
     *
     * @throws RuntimeException
     */
    protected function findEntryIndex(array $entries, string $match): int
    {
        $indexes = array_keys(array_filter($entries, fn (string $entry) => Str::contains($entry, $match, ignoreCase: true)));

        return match (count($indexes)) {
            1 => $indexes[0],
            0 => throw new RuntimeException(__('memory.errors.no_match', ['match' => $match])),
            default => throw new RuntimeException(__('memory.errors.several_matches', ['match' => $match])),
        };
    }

    protected function formatEntry(string $note, ?string $date = null): string
    {
        return '- ['.($date ?? now()->toDateString()).'] '.Str::squish($note);
    }
}
