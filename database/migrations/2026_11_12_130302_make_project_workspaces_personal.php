<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Each user who reaches a project gets their own workspace of opendb in it, `.opendb/users/{id}`, and their own history
 * of the editor: what was shared until now becomes the workspace of the owner of the project.
 */
return new class extends Migration
{
    /**
     * The files of opendb that were shared by the whole project.
     *
     * @var list<string>
     */
    private const array SHARED_ENTRIES = ['sql', 'exports', 'snippets.json', 'erd.json'];

    public function up(): void
    {
        Schema::table('query_history_entries', function (Blueprint $table) {
            $table->foreignIdFor(User::class)->nullable()->after('project_id')->constrained()->cascadeOnDelete();
            $table->index(['project_id', 'user_id', 'id']);
        });

        DB::table('query_history_entries')->update([
            'user_id' => DB::raw('(SELECT user_id FROM projects WHERE projects.id = query_history_entries.project_id)'),
        ]);

        $this->moveSharedEntries(toOwner: true);
    }

    public function down(): void
    {
        $this->moveSharedEntries(toOwner: false);

        Schema::table('query_history_entries', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'user_id', 'id']);
            $table->dropConstrainedForeignIdFor(User::class);
        });
    }

    /**
     * Move the files shared by each project into the workspace of its owner, or back; a file already at its destination
     * is left where it is.
     */
    private function moveSharedEntries(bool $toOwner): void
    {
        DB::table('projects')->whereNotNull('path')->get(['user_id', 'path'])->each(function (object $project) use ($toOwner): void {
            $shared = rtrim($project->path, '/\\').DIRECTORY_SEPARATOR.Project::WORKSPACE_DIRECTORY;
            $owner = $shared.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.$project->user_id;
            [$from, $to] = $toOwner ? [$shared, $owner] : [$owner, $shared];

            foreach (self::SHARED_ENTRIES as $entry) {
                if (! File::exists($from.DIRECTORY_SEPARATOR.$entry) || File::exists($to.DIRECTORY_SEPARATOR.$entry)) {
                    continue;
                }

                File::ensureDirectoryExists($to, 0755);
                File::move($from.DIRECTORY_SEPARATOR.$entry, $to.DIRECTORY_SEPARATOR.$entry);
            }
        });
    }
};
