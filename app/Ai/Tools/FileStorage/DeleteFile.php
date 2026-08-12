<?php

namespace App\Ai\Tools\FileStorage;

use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Filesystem\DeleteFile as AIDeleteFileTool;
use Laravel\Ai\Tools\Request;

class DeleteFile extends AIDeleteFileTool implements Approvable
{
    use InteractsWithApprovals;

    protected function needsApproval(Request $request): Approval|bool
    {
        $path = (string) $request->string('path');
        $rootDir = rtrim($this->disk()->path(''), '/');

        return Approval::required("Cette action supprime le fichier [$path] du repertoire [$rootDir].");
    }
}
