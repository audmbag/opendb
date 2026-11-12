<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Services\Workspace\ProjectWorkspace;
use Illuminate\Filesystem\Filesystem;
use JsonException;

/**
 * The snippets of the editor of a project, kept in `.opendb/snippets.json` next to its .sql files: the snippets the
 * user wrote, and the names of the snippets of opendb they hid. Nothing of them is stored in the database.
 */
class SnippetStore
{
    public const string FILE = 'snippets.json';

    public function __construct(private Filesystem $files) {}

    /**
     * The configuration of the project, empty until the user changes it. A file that cannot be read is reported
     * rather than replaced: it may have been written by hand.
     *
     * @return array{snippets: list<array{label: string, detail: string, template: string}>, hidden: list<string>, invalid: bool}
     */
    public function read(ProjectWorkspace $workspace): array
    {
        $path = $workspace->path(self::FILE);

        if (! $this->files->isFile($path)) {
            return ['snippets' => [], 'hidden' => [], 'invalid' => false];
        }

        try {
            $data = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['snippets' => [], 'hidden' => [], 'invalid' => true];
        }

        if (! is_array($data) || ! is_array($data['snippets'] ?? []) || ! is_array($data['hidden'] ?? [])) {
            return ['snippets' => [], 'hidden' => [], 'invalid' => true];
        }

        return [
            'snippets' => array_values(array_map(fn (mixed $snippet): array => [
                'label' => (string) ($snippet['label'] ?? ''),
                'detail' => (string) ($snippet['detail'] ?? ''),
                'template' => (string) ($snippet['template'] ?? ''),
            ], array_filter($data['snippets'] ?? [], is_array(...)))),
            'hidden' => array_values(array_map(strval(...), $data['hidden'] ?? [])),
            'invalid' => false,
        ];
    }

    /**
     * @param  list<array{label: string, detail: ?string, template: string}>  $snippets
     * @param  list<string>  $hidden
     *
     * @throws SqlFileException when the directory of the project cannot be written
     */
    public function write(ProjectWorkspace $workspace, array $snippets, array $hidden): void
    {
        $directory = dirname($workspace->path(self::FILE));

        if (! $this->files->isDirectory($workspace->projectPath())
            || (! $this->files->isDirectory($directory) && ! $this->files->makeDirectory($directory, 0755, true, true))) {
            throw SqlFileException::unwritableDirectory($directory);
        }

        $this->files->replace($workspace->path(self::FILE), json_encode([
            'snippets' => array_map(fn (array $snippet): array => [
                'label' => $snippet['label'],
                'detail' => (string) ($snippet['detail'] ?? ''),
                'template' => $snippet['template'],
            ], $snippets),
            'hidden' => array_values(array_unique($hidden)),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }
}
