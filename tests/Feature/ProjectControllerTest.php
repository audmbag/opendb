<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemory;
use Illuminate\Support\Facades\Storage;

it('deletes the memory of the project along with the project', function () {
    Storage::fake('opendb');
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $memory = new ProjectMemory($project);
    $memory->add('Les montants de `orders` sont en centimes.');

    $this->actingAs($user)->delete("/projects/{$project->id}")->assertRedirect();

    expect(Project::find($project->id))->toBeNull()
        ->and(Storage::disk('opendb')->exists($memory->path()))->toBeFalse();
});
