<?php

namespace App\Services;

use Symfony\Component\Finder\Finder;

class ProjectFileIndexer
{
    /**
     * Dossiers jamais indexés, même lorsqu'ils ne sont pas ignorés par Git.
     *
     * @var list<string>
     */
    public const EXCLUDED_DIRECTORIES = ['node_modules', 'vendor'];

    public function __construct(protected int $limit = 10000) {}

    /**
     * Liste les fichiers et dossiers du projet relativement à sa racine, en respectant le `.gitignore`.
     * Les dossiers se terminent par un `/`.
     *
     * @return list<string>
     */
    public function index(string $basePath): array
    {
        $realPath = realpath($basePath);

        if ($realPath === false || ! is_dir($realPath)) {
            return [];
        }

        $finder = Finder::create()
            ->in($realPath)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreVCSIgnored(true)
            ->ignoreUnreadableDirs()
            ->exclude(self::EXCLUDED_DIRECTORIES)
            ->sortByName();

        $paths = [];

        foreach ($finder as $file) {
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

            $paths[] = $file->isDir() ? $relativePath.'/' : $relativePath;

            if (count($paths) >= $this->limit) {
                break;
            }
        }

        return $paths;
    }
}
