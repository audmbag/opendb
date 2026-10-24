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
                throw new RuntimeException('La mémoire est pleine ('.self::MAX_ENTRIES.' entrées) : '
                    .'fusionne ou supprime des entrées obsolètes avant d\'en ajouter une nouvelle.');
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
            0 => throw new RuntimeException("Aucune entrée de la mémoire ne contient « {$match} »."),
            default => throw new RuntimeException('Plusieurs entrées de la mémoire contiennent « '.$match.' » : '
                .'précise un extrait plus long pour cibler une seule entrée.'),
        };
    }

    protected function formatEntry(string $note): string
    {
        return '- ['.now()->toDateString().'] '.Str::squish($note);
    }
}
