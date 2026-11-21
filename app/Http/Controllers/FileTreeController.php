<?php

namespace App\Http\Controllers;

use App\Services\FileExplorerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class FileTreeController extends Controller
{
    /**
     * The entries of one folder, and of the folders leading to the revealed path: the picker loads a folder when it is
     * opened. Telling a folder from a file reads each entry, which a hard disk does at a few milliseconds each the first
     * time: preloading the subfolders of the home folder too took seconds on Windows.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $path = $request->input('path', null);

        $extensionsArray = Str::of($request->query('targetFileExtensions'))
            ->explode(',')
            ->map(fn ($ext) => trim($ext))
            ->toArray();

        $onlyFolders = $request->boolean('onlyFolders', false);

        $service = new FileExplorerService(onlyFolders: $onlyFolders, targetFileExtensions: $extensionsArray);

        return Response::json($service->getFolderTree($path, 1, $request->query('reveal')));
    }

    public function getChildren(string $path): array
    {
        $children = [];
        if (is_dir($path)) {
            $dir = opendir($path);
            if ($dir) {
                while (($file = readdir($dir)) !== false) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    $fullPath = $path.DIRECTORY_SEPARATOR.$file;
                    $children[] = [
                        'name' => $file,
                        'path' => $fullPath,
                        'isDirectory' => is_dir($fullPath),
                    ];
                }
                closedir($dir);
            }
        }

        return $children;
    }
}
