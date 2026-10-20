<?php

namespace App\Http\Controllers;

use App\Services\FileExplorerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class FileTreeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $path = $request->input('path', null);

        $extensionsArray = Str::of($request->query('targetFileExtensions'))
            ->explode(',')
            ->map(fn ($ext) => trim($ext))
            ->toArray();

        $onlyFolders = $request->boolean('onlyFolders', false);

        $service = new FileExplorerService(onlyFolders: $onlyFolders, targetFileExtensions: $extensionsArray);

        return Response::json($service->getFolderTree($path, 2, $request->query('reveal')));
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
