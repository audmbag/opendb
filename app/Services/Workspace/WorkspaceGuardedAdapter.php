<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\Project;
use League\Flysystem\Config;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

/**
 * The files of a project directory as the agent of a user reaches them: the workspace of opendb only shows the folder of
 * this user, `.opendb/users/{id}`, and the files of the other users can be neither read, listed nor changed.
 */
final readonly class WorkspaceGuardedAdapter implements FilesystemAdapter
{
    private const string REASON = 'This file belongs to the workspace of another user.';

    /**
     * @param  string  $workspace  the workspace of the user, relative to the project directory
     */
    public function __construct(private FilesystemAdapter $adapter, private string $workspace) {}

    /**
     * Whether the path leads into the workspace of opendb elsewhere than to the folder of the user. The folders leading
     * to it stay visible; the comparison ignores the case, like the file systems of macOS and Windows.
     */
    public function hides(string $path): bool
    {
        $path = strtolower(trim(str_replace('\\', '/', $path), '/'));
        $root = strtolower(Project::WORKSPACE_DIRECTORY);
        $workspace = strtolower($this->workspace);

        if ($path !== $root && ! str_starts_with($path, $root.'/')) {
            return false;
        }

        return ! in_array($path, [$root, $root.'/'.ProjectWorkspace::USERS_DIRECTORY, $workspace], true)
            && ! str_starts_with($path, $workspace.'/');
    }

    public function fileExists(string $path): bool
    {
        return ! $this->hides($path) && $this->adapter->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return ! $this->hides($path) && $this->adapter->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->hides($path) ? throw UnableToWriteFile::atLocation($path, self::REASON) : $this->adapter->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->hides($path) ? throw UnableToWriteFile::atLocation($path, self::REASON) : $this->adapter->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->hides($path) ? throw UnableToReadFile::fromLocation($path, self::REASON) : $this->adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->hides($path) ? throw UnableToReadFile::fromLocation($path, self::REASON) : $this->adapter->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->hides($path) ? throw UnableToDeleteFile::atLocation($path, self::REASON) : $this->adapter->delete($path);
    }

    /**
     * The workspace of opendb itself is never deleted at once: it holds the folders of the other users.
     */
    public function deleteDirectory(string $path): void
    {
        $this->hides($path) || $this->leadsToWorkspaces($path)
            ? throw UnableToDeleteDirectory::atLocation($path, self::REASON)
            : $this->adapter->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->hides($path) ? throw UnableToCreateDirectory::atLocation($path, self::REASON) : $this->adapter->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->hides($path) ? throw UnableToSetVisibility::atLocation($path, self::REASON) : $this->adapter->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->hides($path) ? throw UnableToRetrieveMetadata::visibility($path, self::REASON) : $this->adapter->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->hides($path) ? throw UnableToRetrieveMetadata::mimeType($path, self::REASON) : $this->adapter->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->hides($path) ? throw UnableToRetrieveMetadata::lastModified($path, self::REASON) : $this->adapter->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->hides($path) ? throw UnableToRetrieveMetadata::fileSize($path, self::REASON) : $this->adapter->fileSize($path);
    }

    /**
     * @return iterable<StorageAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        if ($this->hides($path)) {
            return [];
        }

        return (new DirectoryListing($this->adapter->listContents($path, $deep)))
            ->filter(fn (StorageAttributes $item): bool => ! $this->hides($item->path()));
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->hides($source) || $this->hides($destination) || $this->leadsToWorkspaces($source)
            ? throw UnableToMoveFile::because(self::REASON, $source, $destination)
            : $this->adapter->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->hides($source) || $this->hides($destination)
            ? throw UnableToCopyFile::because(self::REASON, $source, $destination)
            : $this->adapter->copy($source, $destination, $config);
    }

    /**
     * Whether the folder holds the workspaces of the other users: the workspace of opendb, or its folder of users.
     */
    private function leadsToWorkspaces(string $path): bool
    {
        $path = strtolower(trim(str_replace('\\', '/', $path), '/'));
        $root = strtolower(Project::WORKSPACE_DIRECTORY);

        return in_array($path, ['', $root, $root.'/'.ProjectWorkspace::USERS_DIRECTORY], true);
    }
}
