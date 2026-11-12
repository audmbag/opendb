<?php

use App\Ai\Tools\Memory\UpdateProjectMemory;
use App\Models\Project;
use App\Services\ProjectMemory;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    Storage::fake('opendb');
    $this->travelTo('2026-10-21 10:00:00');

    $this->project = Project::factory()->create();
    $this->memory = new ProjectMemory($this->project);
});

function updateMemory(ProjectMemory $memory, array $arguments): string
{
    return (string) new UpdateProjectMemory($memory)->handle(new Request($arguments, 'call_1'));
}

function memoryFile(Project $project): string
{
    return Storage::disk('opendb')->get("projects/{$project->id}/MEMORY.md");
}

it('adds a dated entry to the memory file of the project', function () {
    $result = updateMemory($this->memory, ['action' => 'add', 'note' => "Les montants de `orders`\n sont en centimes."]);

    expect($result)->toBe('Project memory updated (`add`).')
        ->and(memoryFile($this->project))->toBe("- [2026-10-21] Les montants de `orders` sont en centimes.\n");
});

it('replaces the single entry containing the match', function () {
    $this->memory->add('Préfère les réponses en anglais.');
    $this->memory->add('Les montants de `orders` sont en centimes.');

    $result = updateMemory($this->memory, ['action' => 'replace', 'match' => 'en ANGLAIS', 'note' => 'Préfère les réponses en français.']);

    expect($result)->toBe('Project memory updated (`replace`).')
        ->and(memoryFile($this->project))->toBe("- [2026-10-21] Préfère les réponses en français.\n- [2026-10-21] Les montants de `orders` sont en centimes.\n");
});

it('removes the single entry containing the match', function () {
    $this->memory->add('Préfère les réponses en anglais.');
    $this->memory->add('Les montants de `orders` sont en centimes.');

    $result = updateMemory($this->memory, ['action' => 'remove', 'match' => 'anglais']);

    expect($result)->toBe('Project memory updated (`remove`).')
        ->and(memoryFile($this->project))->toBe("- [2026-10-21] Les montants de `orders` sont en centimes.\n");
});

it('does not change the memory when no entry contains the match', function () {
    $this->memory->add('Préfère les réponses en anglais.');

    $result = updateMemory($this->memory, ['action' => 'remove', 'match' => 'centimes']);

    expect($result)->toBe('Aucune entrée de la mémoire ne contient « centimes ».')
        ->and(memoryFile($this->project))->toBe("- [2026-10-21] Préfère les réponses en anglais.\n");
});

it('does not change the memory when several entries contain the match', function () {
    $this->memory->add('Préfère les réponses en anglais.');
    $this->memory->add('Préfère les tableaux compacts.');

    $result = updateMemory($this->memory, ['action' => 'replace', 'match' => 'Préfère', 'note' => 'Autre chose.']);

    expect($result)->toStartWith('Plusieurs entrées de la mémoire contiennent « Préfère »')
        ->and(memoryFile($this->project))->toBe("- [2026-10-21] Préfère les réponses en anglais.\n- [2026-10-21] Préfère les tableaux compacts.\n");
});

it('rejects a new entry once the memory is full', function () {
    foreach (range(1, ProjectMemory::MAX_ENTRIES) as $index) {
        $this->memory->add("Fait n°{$index}.");
    }

    $result = updateMemory($this->memory, ['action' => 'add', 'note' => 'Un fait de trop.']);

    expect($result)->toStartWith('La mémoire est pleine')
        ->and(memoryFile($this->project))->not->toContain('Un fait de trop.');
});

it('rejects a call missing the parameter required by the action', function (array $arguments, string $message) {
    expect(updateMemory($this->memory, $arguments))->toBe($message)
        ->and(Storage::disk('opendb')->exists("projects/{$this->project->id}/MEMORY.md"))->toBeFalse();
})->with([
    'add without note' => [['action' => 'add', 'note' => '  '], 'The `note` parameter is required for the `add` action.'],
    'replace without match' => [['action' => 'replace', 'note' => 'Fait.'], 'The `match` parameter is required for the `replace` action.'],
    'unknown action' => [['action' => 'append', 'note' => 'Fait.'], 'Unknown action: use `add`, `replace` or `remove`.'],
]);
