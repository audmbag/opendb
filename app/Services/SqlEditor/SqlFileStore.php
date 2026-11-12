<?php

declare(strict_types=1);

namespace App\Services\SqlEditor;

use App\Services\FileExplorerService;
use App\Services\Workspace\ProjectWorkspace;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Les fichiers .sql des onglets de l'éditeur et les dossiers qui les rangent, dans le dossier `.opendb/sql` du
 * répertoire du projet. Un fichier ou un dossier est désigné par son chemin relatif à ce dossier, ses segments
 * séparés par `/` quel que soit le système : `rapports/ventes.sql`.
 * Leur contenu ne quitte jamais le disque : la version d'un fichier est l'empreinte de son contenu, qui révèle
 * une modification faite en dehors de l'éditeur.
 */
class SqlFileStore
{
    private const string FILE_PATTERN = '/^[\p{L}\p{N}_\- ][\p{L}\p{N}_\- .]{0,115}\.sql$/u';

    /**
     * Ni point au début, qui cacherait le dossier, ni point ou espace à la fin, que Windows retire du nom.
     */
    private const string FOLDER_PATTERN = '/^[\p{L}\p{N}_\-](?:[\p{L}\p{N}_\- .]{0,118}[\p{L}\p{N}_\-])?$/u';

    /**
     * Le nombre de niveaux de l'arbre, fichier compris : l'explorateur les lit tous d'un coup.
     */
    public const int MAX_DEPTH = 12;

    public function __construct(private Filesystem $files) {}

    /**
     * L'arbre des dossiers et des fichiers de l'éditeur, sans leur contenu : seuls les dossiers sont lus.
     *
     * @return list<array{id: string, name: string, type: 'folder'|'file', children?: list<array<string, mixed>>}>
     */
    public function tree(ProjectWorkspace $workspace): array
    {
        $directory = $this->directory($workspace);

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        try {
            $root = new FileExplorerService(allowedBasePaths: [$directory], targetFileExtensions: ['sql'])
                ->getFolderTree($directory, self::MAX_DEPTH);
        } catch (RuntimeException) {
            return [];
        }

        return $this->relativeNodes($root['children'], strlen($root['id']) + 1);
    }

    /**
     * @return array{name: string, sql: string, version: string}
     *
     * @throws SqlFileException
     */
    public function read(ProjectWorkspace $workspace, string $name): array
    {
        $sql = $this->files->get($this->existingFile($workspace, $name));

        return ['name' => $name, 'sql' => $sql, 'version' => $this->version($sql)];
    }

    /**
     * Un nouveau fichier dans le dossier donné, vide ou avec la requête donnée, nommé d'après le premier numéro
     * libre : sans-titre-1.sql, sans-titre-2.sql… (le mot suit la langue de l'interface)
     *
     * @return array{name: string, sql: string, version: string}
     *
     * @throws SqlFileException
     */
    public function create(ProjectWorkspace $workspace, string $sql = '', ?string $folder = null): array
    {
        $name = $this->freeName($workspace, $folder, __('sql_editor.untitled_file'), '.sql');

        $this->files->put($this->path($workspace, $name), $sql);

        return ['name' => $name, 'sql' => $sql, 'version' => $this->version($sql)];
    }

    /**
     * Un nouveau dossier dans le dossier donné, nommé d'après le premier numéro libre : nouveau-dossier-1…
     *
     * @return array{name: string}
     *
     * @throws SqlFileException
     */
    public function createFolder(ProjectWorkspace $workspace, ?string $parent = null): array
    {
        $name = $this->freeName($workspace, $parent, __('sql_editor.untitled_folder'), '');

        $this->files->makeDirectory($this->path($workspace, $name), 0755, true, true);

        return ['name' => $name];
    }

    /**
     * Écrit le contenu de l'onglet. Avec la version que l'éditeur a lue, refuse d'écraser un fichier modifié depuis.
     *
     * @return array{name: string, version: string}
     *
     * @throws SqlFileException
     */
    public function write(ProjectWorkspace $workspace, string $name, string $sql, ?string $expectedVersion = null): array
    {
        $path = $this->existingFile($workspace, $name);

        if ($expectedVersion !== null && $this->version($this->files->get($path)) !== $expectedVersion) {
            throw SqlFileException::changedOnDisk($name);
        }

        $this->files->replace($path, $sql);

        return ['name' => $name, 'version' => $this->version($sql)];
    }

    /**
     * Renomme le fichier, ou le déplace quand le nouveau chemin est dans un autre dossier.
     *
     * @return array{name: string, version: string}
     *
     * @throws SqlFileException
     */
    public function rename(ProjectWorkspace $workspace, string $name, string $newName): array
    {
        $path = $this->existingFile($workspace, $name);

        if (! $this->isValidName($newName)) {
            throw SqlFileException::invalidName();
        }

        $this->move($workspace, $path, $name, $newName);

        return ['name' => $newName, 'version' => $this->version($this->files->get($this->path($workspace, $newName)))];
    }

    /**
     * Renomme le dossier avec tout ce qu'il contient, ou le déplace dans un autre dossier.
     *
     * @return array{name: string}
     *
     * @throws SqlFileException
     */
    public function renameFolder(ProjectWorkspace $workspace, string $name, string $newName): array
    {
        $path = $this->existingFolder($workspace, $name);

        if (! $this->isValidFolder($newName)) {
            throw SqlFileException::invalidFolderName();
        }

        if (str_starts_with(mb_strtolower($newName), mb_strtolower($name).'/')) {
            throw SqlFileException::folderIntoItself($name);
        }

        $this->move($workspace, $path, $name, $newName, folder: true);

        return ['name' => $newName];
    }

    /**
     * @throws SqlFileException
     */
    public function delete(ProjectWorkspace $workspace, string $name): void
    {
        $this->files->delete($this->existingFile($workspace, $name));
    }

    /**
     * Supprime le dossier avec tout ce qu'il contient.
     *
     * @throws SqlFileException
     */
    public function deleteFolder(ProjectWorkspace $workspace, string $name): void
    {
        $this->files->deleteDirectory($this->existingFolder($workspace, $name));
    }

    public function directory(ProjectWorkspace $workspace): string
    {
        return $workspace->path('sql');
    }

    /**
     * Le chemin d'un fichier .sql de l'éditeur, dans ses dossiers éventuels.
     */
    public function isValidName(string $name): bool
    {
        $segments = explode('/', $name);
        $file = array_pop($segments);

        return count($segments) < self::MAX_DEPTH
            && preg_match(self::FILE_PATTERN, $file) === 1
            && ($segments === [] || $this->isValidFolder(implode('/', $segments)));
    }

    /**
     * Le chemin d'un dossier de l'éditeur, qui peut encore recevoir un fichier sans dépasser la profondeur lue.
     */
    public function isValidFolder(string $name): bool
    {
        $segments = explode('/', $name);

        return count($segments) < self::MAX_DEPTH
            && array_all($segments, fn (string $segment): bool => preg_match(self::FOLDER_PATTERN, $segment) === 1);
    }

    /**
     * Les nœuds de l'explorateur de fichiers, désignés par leur chemin relatif au dossier de l'éditeur. Ce que
     * l'éditeur ne pourrait pas ouvrir, comme un nom en majuscules ou avec un caractère spécial, n'est pas montré.
     *
     * @param  list<array{id: string, name: string, type: string, children?: array<mixed>}>  $nodes
     * @return list<array{id: string, name: string, type: 'folder'|'file', children?: list<array<string, mixed>>}>
     */
    private function relativeNodes(array $nodes, int $offset): array
    {
        $relative = [];

        foreach ($nodes as $node) {
            $id = str_replace(DIRECTORY_SEPARATOR, '/', substr($node['id'], $offset));

            if ($node['type'] === 'folder' && $this->isValidFolder($id)) {
                $relative[] = ['id' => $id, 'name' => $node['name'], 'type' => 'folder', 'children' => $this->relativeNodes($node['children'] ?? [], $offset)];
            } elseif ($node['type'] === 'file' && $this->isValidName($id)) {
                $relative[] = ['id' => $id, 'name' => $node['name'], 'type' => 'file'];
            }
        }

        return $relative;
    }

    /**
     * Le premier nom `{mot}-{numéro}{suffixe}` libre dans le dossier, qui est créé s'il n'existe pas encore.
     *
     * @throws SqlFileException
     */
    private function freeName(ProjectWorkspace $workspace, ?string $folder, string $word, string $suffix): string
    {
        $folder = blank($folder) ? null : trim($folder, '/');

        if ($folder !== null && ! $this->isValidFolder($folder)) {
            throw SqlFileException::invalidFolderName();
        }

        $this->ensureDirectory($workspace, $folder);

        $prefix = $folder === null ? '' : "{$folder}/";
        $number = 1;

        while ($this->files->exists($this->path($workspace, "{$prefix}{$word}-{$number}{$suffix}"))) {
            $number++;
        }

        return "{$prefix}{$word}-{$number}{$suffix}";
    }

    /**
     * Déplace un fichier ou un dossier vers un autre chemin, en créant au besoin le dossier qui le reçoit.
     *
     * @throws SqlFileException
     */
    private function move(ProjectWorkspace $workspace, string $from, string $name, string $newName, bool $folder = false): void
    {
        if ($newName === $name) {
            return;
        }

        // Une simple différence de casse désigne le même chemin sur un système de fichiers insensible à la casse.
        if (strcasecmp($newName, $name) !== 0 && $this->files->exists($this->path($workspace, $newName))) {
            throw $folder ? SqlFileException::folderAlreadyExists($newName) : SqlFileException::alreadyExists($newName);
        }

        $parent = str_contains($newName, '/') ? mb_substr($newName, 0, mb_strrpos($newName, '/')) : null;

        $this->ensureDirectory($workspace, $parent);
        $this->files->move($from, $this->path($workspace, $newName));
    }

    private function version(string $sql): string
    {
        return hash('xxh128', $sql);
    }

    private function path(ProjectWorkspace $workspace, string $name): string
    {
        return $this->directory($workspace).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
    }

    /**
     * @throws SqlFileException when the name is not the name of a file of the editor
     */
    private function existingFile(ProjectWorkspace $workspace, string $name): string
    {
        if (! $this->isValidName($name)) {
            throw SqlFileException::invalidName();
        }

        $path = $this->path($workspace, $name);

        if (! $this->files->isFile($path)) {
            throw SqlFileException::notFound($name);
        }

        return $path;
    }

    /**
     * @throws SqlFileException when the name is not the name of a folder of the editor
     */
    private function existingFolder(ProjectWorkspace $workspace, string $name): string
    {
        if (! $this->isValidFolder($name)) {
            throw SqlFileException::invalidFolderName();
        }

        $path = $this->path($workspace, $name);

        if (! $this->files->isDirectory($path)) {
            throw SqlFileException::folderNotFound($name);
        }

        return $path;
    }

    /**
     * Le dossier de l'éditeur, ou l'un de ses sous-dossiers, prêt à recevoir un fichier.
     *
     * @throws SqlFileException
     */
    private function ensureDirectory(ProjectWorkspace $workspace, ?string $folder = null): void
    {
        $directory = $folder === null ? $this->directory($workspace) : $this->path($workspace, $folder);

        if (! $this->files->isDirectory($workspace->projectPath())
            || (! $this->files->isDirectory($directory) && ! $this->files->makeDirectory($directory, 0755, true, true))
            || ! $this->files->isWritable($directory)) {
            throw SqlFileException::unwritableDirectory($directory);
        }
    }
}
