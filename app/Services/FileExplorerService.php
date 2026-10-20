<?php

namespace App\Services;

use RuntimeException;

class FileExplorerService
{
    protected ?array $allowedBasePaths;

    protected bool $onlyFolders;

    protected array $targetFileExtensions;

    public function __construct(
        ?array $allowedBasePaths = null,
        bool $onlyFolders = false,
        array $targetFileExtensions = [],
        protected SystemInfoService $systemInfo = new SystemInfoService,
    ) {
        $this->allowedBasePaths = $allowedBasePaths ?? [$this->getUserHomeDirectory()];

        $this->onlyFolders = $onlyFolders;
        $this->targetFileExtensions = $targetFileExtensions;
    }

    /**
     * @param  string|null  $path  Chemin absolu du dossier à explorer
     * @param  int  $depth  Nombre de niveaux à précharger (2 = enfants + petits-enfants)
     * @param  string|null  $reveal  Chemin à rendre visible : chaque dossier menant à ce chemin voit ses enfants préchargés
     * @return array{id: string, name: string, type: string, children: array}
     */
    public function getFolderTree(?string $path = null, int $depth = 2, ?string $reveal = null): array
    {
        $path = $path ?? $this->getUserHomeDirectory();

        $realPath = $this->resolveAndAuthorize($path);

        return [
            'id' => $realPath,
            'name' => basename($realPath),
            'type' => 'folder',
            'children' => $this->getChildren($realPath, $depth, blank($reveal) ? null : rtrim($reveal, DIRECTORY_SEPARATOR)),
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
    protected function getChildren(string $path, int $depth, ?string $reveal = null): array
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

                $isOnRevealPath = $reveal !== null && $this->isSameOrParentPath($childPath, $reveal);

                $node['children'] = $isOnRevealPath
                    ? $this->getChildren($childPath, max($depth - 1, 1), $reveal)
                    : $this->getChildren($childPath, $depth - 1);

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

    protected function isSameOrParentPath(string $parent, string $path): bool
    {
        return $path === $parent || str_starts_with($path, $parent.DIRECTORY_SEPARATOR);
    }

    public function getUserHomeDirectory(): string
    {
        return $this->systemInfo->homeDirectory();
    }
}
