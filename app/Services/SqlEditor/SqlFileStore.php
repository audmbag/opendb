<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Models\Project;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Les fichiers .sql des onglets de l'éditeur, rangés dans le dossier `.opendb/sql` du répertoire du projet.
 * Leur contenu ne quitte jamais le disque : la version d'un fichier est l'empreinte de son contenu, qui révèle
 * une modification faite en dehors de l'éditeur.
 */
class SqlFileStore
{
    private const string NAME_PATTERN = '/^[\p{L}\p{N}_\- ][\p{L}\p{N}_\- .]{0,115}\.sql$/u';

    private const string UNTITLED = 'sans-titre';

    public function __construct(private Filesystem $files) {}

    /**
     * The files of the editor, without their content: listing them reads only the directory.
     *
     * @return list<array{name: string, size: int, modifiedAt: int}>
     */
    public function list(Project $project): array
    {
        $directory = $this->directory($project);

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $files = array_values(array_filter(
            $this->files->files($directory),
            fn (SplFileInfo $file): bool => $this->isValidName($file->getFilename()),
        ));

        usort($files, fn (SplFileInfo $a, SplFileInfo $b): int => strnatcasecmp($a->getFilename(), $b->getFilename()));

        return array_map(fn (SplFileInfo $file): array => [
            'name' => $file->getFilename(),
            'size' => $file->getSize(),
            'modifiedAt' => $file->getMTime(),
        ], $files);
    }

    /**
     * @return array{name: string, sql: string, version: string}
     *
     * @throws SqlFileException
     */
    public function read(Project $project, string $name): array
    {
        $path = $this->existingPath($project, $name);
        $sql = $this->files->get($path);

        return ['name' => $name, 'sql' => $sql, 'version' => $this->version($sql)];
    }

    /**
     * Un nouveau fichier, vide ou avec la requête donnée, nommé d'après le premier numéro libre : sans-titre-1.sql, sans-titre-2.sql…
     *
     * @return array{name: string, sql: string, version: string}
     *
     * @throws SqlFileException
     */
    public function create(Project $project, string $sql = ''): array
    {
        $this->ensureDirectory($project);

        $number = 1;

        while ($this->files->exists($this->path($project, self::UNTITLED."-{$number}.sql"))) {
            $number++;
        }

        $name = self::UNTITLED."-{$number}.sql";
        $this->files->put($this->path($project, $name), $sql);

        return ['name' => $name, 'sql' => $sql, 'version' => $this->version($sql)];
    }

    /**
     * Écrit le contenu de l'onglet. Avec la version que l'éditeur a lue, refuse d'écraser un fichier modifié depuis.
     *
     * @return array{name: string, version: string}
     *
     * @throws SqlFileException
     */
    public function write(Project $project, string $name, string $sql, ?string $expectedVersion = null): array
    {
        $path = $this->existingPath($project, $name);

        if ($expectedVersion !== null && $this->version($this->files->get($path)) !== $expectedVersion) {
            throw SqlFileException::changedOnDisk($name);
        }

        $this->files->replace($path, $sql);

        return ['name' => $name, 'version' => $this->version($sql)];
    }

    /**
     * @return array{name: string, version: string}
     *
     * @throws SqlFileException
     */
    public function rename(Project $project, string $name, string $newName): array
    {
        $path = $this->existingPath($project, $name);

        if (! $this->isValidName($newName)) {
            throw SqlFileException::invalidName();
        }

        if ($newName !== $name) {
            // Une simple différence de casse désigne le même fichier sur un système de fichiers insensible à la casse.
            if (strcasecmp($newName, $name) !== 0 && $this->files->exists($this->path($project, $newName))) {
                throw SqlFileException::alreadyExists($newName);
            }

            $this->files->move($path, $this->path($project, $newName));
        }

        return ['name' => $newName, 'version' => $this->version($this->files->get($this->path($project, $newName)))];
    }

    /**
     * @throws SqlFileException
     */
    public function delete(Project $project, string $name): void
    {
        $this->files->delete($this->existingPath($project, $name));
    }

    public function directory(Project $project): string
    {
        return $project->workspacePath('sql');
    }

    public function isValidName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    private function version(string $sql): string
    {
        return hash('xxh128', $sql);
    }

    private function path(Project $project, string $name): string
    {
        return $this->directory($project).DIRECTORY_SEPARATOR.$name;
    }

    /**
     * @throws SqlFileException when the name is not the name of a file of the editor
     */
    private function existingPath(Project $project, string $name): string
    {
        if (! $this->isValidName($name)) {
            throw SqlFileException::invalidName();
        }

        $path = $this->path($project, $name);

        if (! $this->files->isFile($path)) {
            throw SqlFileException::notFound($name);
        }

        return $path;
    }

    /**
     * @throws SqlFileException
     */
    private function ensureDirectory(Project $project): void
    {
        $directory = $this->directory($project);

        if (! $this->files->isDirectory((string) $project->path)
            || (! $this->files->isDirectory($directory) && ! $this->files->makeDirectory($directory, 0755, true, true))
            || ! $this->files->isWritable($directory)) {
            throw SqlFileException::unwritableDirectory($directory);
        }
    }
}
