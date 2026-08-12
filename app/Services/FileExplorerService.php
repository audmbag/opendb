<?php

namespace App\Services;

use RuntimeException;

class FileExplorerService
{
    protected ?array $allowedBasePaths;

    protected bool $onlyFolders;

    protected array $targetFileExtensions;

    public function __construct(?array $allowedBasePaths = null, bool $onlyFolders = false, array $targetFileExtensions = [])
    {
        $this->allowedBasePaths = $allowedBasePaths ?? [$this->getUserHomeDirectory()];

        $this->onlyFolders = $onlyFolders;
        $this->targetFileExtensions = $targetFileExtensions;
    }

    /**
     * @param  string|null  $path  Chemin absolu du dossier à explorer
     * @param  int  $depth  Nombre de niveaux à précharger (2 = enfants + petits-enfants)
     */
    public function getFolderTree(?string $path = null, int $depth = 2): array
    {
        $path = $path ?? $this->getUserHomeDirectory();

        $realPath = $this->resolveAndAuthorize($path);

        return [
            'id' => $realPath,
            'name' => basename($realPath),
            'type' => 'folder',
            'children' => $this->getChildren($realPath, $depth),
        ];
    }

    protected function resolveAndAuthorize(string $path): string
    {
        $realPath = realpath($path);

        if ($realPath === false || ! is_dir($realPath)) {
            throw new RuntimeException("Le dossier [{$path}] n'existe pas ou n'est pas accessible.");
        }

        if ($this->allowedBasePaths !== null) {
            $isAllowed = collect($this->allowedBasePaths)
                ->map(fn ($base) => realpath($base))
                ->filter()
                ->contains(fn ($base) => str_starts_with($realPath.DIRECTORY_SEPARATOR, rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR));

            if (! $isAllowed) {
                throw new RuntimeException("Accès refusé au dossier [{$path}].");
            }
        }

        return $realPath;
    }

    /**
     * @return array<int, array{id: string, name: string, type: string, children?: array}>
     */
    protected function getChildren(string $path, int $depth): array
    {
        if ($depth <= 0 || ! is_readable($path)) {
            return [];
        }

        $entries = scandir($path);
        if ($entries === false) {
            return [];
        }

        $folders = [];
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $childPath = $path.DIRECTORY_SEPARATOR.$entry;

            if (! is_readable($childPath)) {
                continue;
            }

            if (is_dir($childPath)) {
                $node = [
                    'id' => $childPath,
                    'name' => $entry,
                    'type' => 'folder',
                ];

                $node['children'] = $this->getChildren($childPath, $depth - 1);

                $folders[] = $node;
            } else {
                if ($this->onlyFolders) {
                    continue;
                }

                if (! empty($this->targetFileExtensions)) {
                    $extension = pathinfo($entry, PATHINFO_EXTENSION);
                    if (! in_array(strtolower($extension), array_map('strtolower', $this->targetFileExtensions))) {
                        continue;
                    }
                }

                $files[] = [
                    'id' => $childPath,
                    'name' => $entry,
                    'type' => 'file',
                ];
            }
        }

        usort($folders, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return [...$folders, ...$files];
    }

    public function getUserHomeDirectory(): string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $info = posix_getpwuid(posix_getuid());
            if (! empty($info['dir'])) {
                return $info['dir'];
            }
        }

        $home = getenv('HOME');
        if ($home !== false && $home !== '') {
            return $home;
        }

        $home = getenv('USERPROFILE');
        if ($home !== false && $home !== '') {
            return $home;
        }

        $homeDrive = getenv('HOMEDRIVE');
        $homePath = getenv('HOMEPATH');

        if ($homeDrive !== false && $homePath !== false) {
            return $homeDrive.$homePath;
        }

        throw new RuntimeException('Unable to determine the user\'s home directory.');
    }
}
