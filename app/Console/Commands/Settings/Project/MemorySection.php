<?php

namespace App\Console\Commands\Settings\Project;

use App\Console\Commands\Settings\ProjectSection;
use App\Http\Requests\Project\ProjectMemoryRequest;
use App\Models\Project;
use App\Services\ProjectMemory;
use Illuminate\Support\Str;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * What the agent remembers about the project from one conversation to the next.
 */
class MemorySection extends ProjectSection
{
    public function label(): string
    {
        return __('projects.settings.nav.memory');
    }

    public function run(Project $project): void
    {
        $memory = new ProjectMemory($project);

        $this->menu(
            label: __('projects.settings.memory.title'),
            actions: fn (): array => [
                'add' => __('console.project_settings.memory.add'),
                ...($memory->notes() !== [] ? [
                    'edit' => __('console.project_settings.memory.edit'),
                    'remove' => __('projects.settings.memory.remove_entry'),
                    'clear' => __('projects.settings.memory.clear_title'),
                ] : []),
            ],
            run: fn (string $action) => match ($action) {
                'add' => $this->add($memory),
                'edit' => $this->edit($memory),
                'remove' => $this->remove($memory),
                'clear' => $this->clear($memory),
            },
            overview: fn () => $this->showNotes($memory),
        );
    }

    private function showNotes(ProjectMemory $memory): void
    {
        $notes = $memory->notes();

        if ($notes === []) {
            warning(__('projects.settings.memory.empty'));

            return;
        }

        table(
            ['#', __('console.project_settings.memory.date'), __('console.project_settings.memory.note')],
            array_map(fn (int $index, array $note): array => [$index + 1, $note['date'] ?? '—', $note['note']], array_keys($notes), $notes),
        );
    }

    private function add(ProjectMemory $memory): void
    {
        $note = text(__('console.project_settings.memory.note'), placeholder: __('projects.settings.memory.placeholder'), required: true);

        $this->save($memory, [...$memory->notes(), ['date' => null, 'note' => $note]]);
    }

    /**
     * A changed entry takes the date of the day, as when the agent rewrites it.
     */
    private function edit(ProjectMemory $memory): void
    {
        $notes = $memory->notes();
        $index = $this->chooseEntry($notes, __('console.project_settings.memory.edit'));

        $notes[$index] = ['date' => null, 'note' => text(__('console.project_settings.memory.note'), default: $notes[$index]['note'], required: true)];

        $this->save($memory, $notes);
    }

    private function remove(ProjectMemory $memory): void
    {
        $notes = $memory->notes();

        unset($notes[$this->chooseEntry($notes, __('projects.settings.memory.remove_entry'))]);

        $this->save($memory, array_values($notes));
    }

    private function clear(ProjectMemory $memory): void
    {
        if (confirm(__('projects.settings.memory.clear_title'), default: false, hint: __('projects.settings.memory.clear_description'))) {
            $this->save($memory, []);
        }
    }

    /**
     * The index of the chosen entry. The options are numbered from 1 like the table, so that the choice gives their key.
     *
     * @param  list<array{date: ?string, note: string}>  $notes
     */
    private function chooseEntry(array $notes, string $label): int
    {
        $options = array_combine(range(1, count($notes)), array_map(fn (array $note): string => Str::limit($note['note'], 80), $notes));

        return (int) select($label, $options, scroll: 10) - 1;
    }

    /**
     * @param  list<array{date: ?string, note: string}>  $notes
     */
    private function save(ProjectMemory $memory, array $notes): void
    {
        $request = $this->validate(ProjectMemoryRequest::class, ['entries' => $notes]);

        if ($request === null) {
            return;
        }

        try {
            $memory->sync($request->entries());
        } catch (RuntimeException $exception) {
            error($exception->getMessage());

            return;
        }

        info(__('projects.messages.memory_saved'));
    }
}
