<?php

use App\Ai\Services\AnswerCancellation;
use App\Ai\Services\AnswersInProgress;
use App\Models\Project;
use App\Models\User;
use App\Services\SSH\TunnelUsage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('disconnect-').'.sqlite';
    $this->user = User::factory()->create();
    $this->project = projectWithSqliteDatabase($this->user, $this->databasePath, shopDatabase(...));
});

afterEach(function () {
    @unlink($this->databasePath);
});

/**
 * @param  array<string, mixed>  $data
 */
function disconnect(array $data = [], ?User $user = null)
{
    return test()->actingAs($user ?? test()->user)->post('/'.test()->project->id.'/disconnect', $data);
}

function answeringConversation(User $user, Project $project): string
{
    $id = (string) Str::uuid();
    $user->conversations()->create(['id' => $id, 'project_id' => $project->id]);
    app(AnswersInProgress::class)->start($id);

    return $id;
}

it('brings the user back to their projects', function () {
    disconnect()->assertRedirect('/');
});

it('interrupts the runs of the SQL editor the page still waits for', function () {
    $runId = fake()->uuid();
    $runKey = "sql-editor:run:{$this->project->id}:{$runId}";
    cache()->put($runKey, '42');

    disconnect(['runs' => [$runId]])->assertRedirect('/');

    expect(cache()->has($runKey))->toBeFalse();
});

it('requires the ids of the runs to be uuids', function () {
    disconnect(['runs' => ['x']])->assertSessionHasErrors('runs.0');
});

it('stops only the answers the agent still gives the user in the project', function () {
    $answering = answeringConversation($this->user, $this->project);
    $otherUser = User::factory()->create();
    $answeringOtherUser = answeringConversation($otherUser, $this->project);
    $answeringOtherProject = answeringConversation($this->user, Project::factory()->for($this->user)->create());

    disconnect()->assertRedirect('/');

    $cancellation = app(AnswerCancellation::class);
    expect($cancellation->isRequested($answering))->toBeTrue()
        ->and($cancellation->isRequested($answeringOtherUser))->toBeFalse()
        ->and($cancellation->isRequested($answeringOtherProject))->toBeFalse();
});

it('leaves the answers already over alone', function () {
    $conversation = answeringConversation($this->user, $this->project);
    app(AnswersInProgress::class)->finish($conversation);

    disconnect()->assertRedirect('/');

    expect(app(AnswerCancellation::class)->isRequested($conversation))->toBeFalse();
});

it('keeps the project of another user out of reach', function () {
    $answering = answeringConversation($this->user, $this->project);

    disconnect(user: User::factory()->create())->assertForbidden();

    expect(app(AnswerCancellation::class)->isRequested($answering))->toBeFalse();
});

describe('ssh master', function () {
    it('counts as used while another process holds it', function () {
        $thisProcess = new TunnelUsage(cache()->store());
        $otherProcess = new TunnelUsage(cache()->store());

        $thisProcess->hold('deploy@bastion.test:22');

        expect($thisProcess->isUsedElsewhere('deploy@bastion.test:22'))->toBeFalse()
            ->and($otherProcess->isUsedElsewhere('deploy@bastion.test:22'))->toBeTrue();

        $thisProcess->release('deploy@bastion.test:22');

        expect($otherProcess->isUsedElsewhere('deploy@bastion.test:22'))->toBeFalse();
    });

    it('ignores a release without a hold', function () {
        $thisProcess = new TunnelUsage(cache()->store());
        $otherProcess = new TunnelUsage(cache()->store());

        $otherProcess->hold('deploy@bastion.test:22');
        $thisProcess->release('deploy@bastion.test:22');

        expect($thisProcess->isUsedElsewhere('deploy@bastion.test:22'))->toBeTrue();
    });
});
