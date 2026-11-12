<?php

declare(strict_types=1);

namespace App\Services\Erd;

use App\Services\SqlEditor\SqlFileException;
use App\Services\Workspace\ProjectWorkspace;
use Illuminate\Filesystem\Filesystem;
use JsonException;

/**
 * The place of each table the user moved in the diagram of a project, kept in `.opendb/erd.json`. The tables never
 * moved are absent: the diagram lays them out itself.
 */
class ErdLayoutStore
{
    public const string FILE = 'erd.json';

    public function __construct(private Filesystem $files) {}

    /**
     * The saved positions, empty when the file is missing or cannot be read: the diagram is then laid out again.
     *
     * @return list<array{table: string, x: float, y: float}>
     */
    public function read(ProjectWorkspace $workspace): array
    {
        $path = $workspace->path(self::FILE);

        if (! $this->files->isFile($path)) {
            return [];
        }

        try {
            $data = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $positions = is_array($data) && is_array($data['positions'] ?? null) ? $data['positions'] : [];
        $layout = [];

        foreach ($positions as $table => $position) {
            if (is_array($position) && is_numeric($position['x'] ?? null) && is_numeric($position['y'] ?? null)) {
                $layout[] = ['table' => (string) $table, 'x' => (float) $position['x'], 'y' => (float) $position['y']];
            }
        }

        return $layout;
    }

    /**
     * Replace the saved positions. Without any, the file is removed so that the diagram is laid out again.
     *
     * @param  list<array{table: string, x: float|int|string, y: float|int|string}>  $positions
     *
     * @throws SqlFileException when the directory of the project cannot be written
     */
    public function write(ProjectWorkspace $workspace, array $positions): void
    {
        $path = $workspace->path(self::FILE);
        $directory = dirname($path);

        if (! $this->files->isDirectory($workspace->projectPath())
            || (! $this->files->isDirectory($directory) && ! $this->files->makeDirectory($directory, 0755, true, true))) {
            throw SqlFileException::unwritableDirectory($directory);
        }

        if ($positions === []) {
            $this->files->delete($path);

            return;
        }

        $byTable = [];

        foreach ($positions as $position) {
            $byTable[$position['table']] = ['x' => round((float) $position['x'], 2), 'y' => round((float) $position['y'], 2)];
        }

        ksort($byTable);

        $this->files->replace($path, json_encode(
            ['positions' => $byTable],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        )."\n");
    }
}
