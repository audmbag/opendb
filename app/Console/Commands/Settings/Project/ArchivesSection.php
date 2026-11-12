<?php

namespace App\Console\Commands\Settings\Project;

use App\Console\Commands\Settings\ProjectSection;
use App\Models\Project;
use App\Repository\ConversationRepository;
use Illuminate\Support\Collection;
use Laravel\Ai\Models\Conversation;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * The conversations of the project the user archived, which they can restore or delete for good.
 */
class ArchivesSection extends ProjectSection
{
    public function __construct(protected ConversationRepository $conversations) {}

    public function label(): string
    {
        return __('projects.settings.nav.archives');
    }

    public function run(Project $project): void
    {
        $this->menu(
            label: __('projects.settings.nav.archives'),
            actions: fn (): array => $this->conversations->archived($project)->isEmpty() ? [] : [
                'restore' => __('console.project_settings.archives.restore'),
                'delete' => __('console.project_settings.archives.delete'),
                'delete_all' => __('conversations.delete_all_title'),
            ],
            run: fn (string $action) => match ($action) {
                'restore' => $this->restore($project),
                'delete' => $this->delete($project),
                'delete_all' => $this->deleteAll($project),
            },
            overview: fn () => $this->showArchives($this->conversations->archived($project)),
        );
    }

    /**
     * @param  Collection<int, Conversation>  $archives
     */
    private function showArchives(Collection $archives): void
    {
        if ($archives->isEmpty()) {
            warning(__('conversations.no_archives'));

            return;
        }

        table(
            [__('console.project_settings.archives.title'), __('console.project_settings.archives.messages'), __('console.project_settings.archives.archived')],
            $archives->map(fn (Conversation $conversation): array => [
                $conversation->title ?? '—',
                $conversation->messages_count,
                $conversation->updated_at?->diffForHumans() ?? '—',
            ])->all(),
        );
    }

    private function restore(Project $project): void
    {
        $conversation = $this->chooseArchive($project, __('console.project_settings.archives.restore'));

        $this->conversations->restore($conversation);

        info(__('console.project_settings.archives.restored', ['title' => $conversation->title]));
    }

    private function delete(Project $project): void
    {
        $conversation = $this->chooseArchive($project, __('console.project_settings.archives.delete'));

        if (! confirm(__('console.project_settings.archives.delete'), default: false, hint: __('conversations.delete_archived_description', ['title' => $conversation->title]))) {
            return;
        }

        $this->conversations->delete($conversation);

        info(trans_choice('conversations.notices.archives_deleted', 1));
    }

    private function deleteAll(Project $project): void
    {
        $count = $this->conversations->archived($project)->count();

        if (! confirm(__('conversations.delete_all_title'), default: false, hint: __('conversations.delete_all_description', ['count' => $count]))) {
            return;
        }

        info(trans_choice('conversations.notices.archives_deleted', $this->conversations->deleteArchived($project)));
    }

    /**
     * Only the archives of the user in the project are offered, so the chosen one is always theirs.
     */
    private function chooseArchive(Project $project, string $label): Conversation
    {
        $archives = $this->conversations->archived($project)->keyBy('id');

        return $archives->get(select($label, $archives->map(fn (Conversation $conversation): string => $conversation->title ?? '—')->all(), scroll: 10));
    }
}
