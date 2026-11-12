<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function archivedConversation(User $user, Project $project, array $attributes = []): Conversation
{
    return $user->conversations()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $project->id,
        'is_archived' => true,
        ...$attributes,
    ]);
}

function addMessage(Conversation $conversation): ConversationMessage
{
    return ConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'agent' => SQLDatabaseAgent::class,
        'role' => 'user',
        'content' => 'Bonjour',
        'attachments' => [],
        'steps' => [],
        'usage' => [],
        'meta' => [],
        'status' => MessageStatus::Completed,
    ]);
}

it('lists the conversations of the user archived in the project', function () {
    $archived = archivedConversation($this->user, $this->project, ['title' => 'Ventes']);
    addMessage($archived);
    archivedConversation($this->user, $this->project, ['is_archived' => false]);
    archivedConversation($this->user, Project::factory()->for($this->user)->create());
    archivedConversation(User::factory()->create(), $this->project);

    $this->actingAs($this->user)
        ->getJson("/projects/{$this->project->id}/settings/archived-conversations")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $archived->id)
        ->assertJsonPath('data.0.messages_count', 1);
});

it('restores an archived conversation in the project', function () {
    $conversation = archivedConversation($this->user, $this->project);

    $this->actingAs($this->user)
        ->patchJson("/projects/{$this->project->id}/settings/archived-conversations/{$conversation->id}/restore")
        ->assertOk()
        ->assertJsonPath('conversation.is_archived', false);

    $this->actingAs($this->user)->get("/{$this->project->id}/chat/{$conversation->id}")->assertOk();
});

it('deletes an archived conversation with its messages', function () {
    $conversation = archivedConversation($this->user, $this->project);
    addMessage($conversation);

    $this->actingAs($this->user)
        ->deleteJson("/projects/{$this->project->id}/settings/archived-conversations/{$conversation->id}")
        ->assertNoContent();

    expect(Conversation::query()->whereKey($conversation->id)->exists())->toBeFalse()
        ->and(ConversationMessage::query()->where('conversation_id', $conversation->id)->exists())->toBeFalse();
});

it('deletes every conversation of the user archived in the project', function () {
    $archived = archivedConversation($this->user, $this->project);
    addMessage($archived);
    $kept = [
        archivedConversation($this->user, $this->project, ['is_archived' => false])->id,
        archivedConversation($this->user, Project::factory()->for($this->user)->create())->id,
        archivedConversation(User::factory()->create(), $this->project)->id,
    ];

    $this->actingAs($this->user)
        ->deleteJson("/projects/{$this->project->id}/settings/archived-conversations")
        ->assertOk();

    expect(Conversation::query()->pluck('id')->sort()->values()->all())->toBe(collect($kept)->sort()->values()->all())
        ->and(ConversationMessage::query()->where('conversation_id', $archived->id)->exists())->toBeFalse();
});

it('refuses to touch a conversation of another user', function (string $method, string $path) {
    $conversation = archivedConversation(User::factory()->create(), $this->project);

    $this->actingAs($this->user)
        ->json($method, "/projects/{$this->project->id}/settings/archived-conversations/{$conversation->id}{$path}")
        ->assertForbidden();

    expect($conversation->refresh()->is_archived)->toBeTruthy();
})->with([
    'restore' => ['PATCH', '/restore'],
    'delete' => ['DELETE', ''],
]);

it('refuses to touch a conversation of another project', function (string $method, string $path) {
    $conversation = archivedConversation($this->user, Project::factory()->for($this->user)->create());

    $this->actingAs($this->user)
        ->json($method, "/projects/{$this->project->id}/settings/archived-conversations/{$conversation->id}{$path}")
        ->assertNotFound();

    expect($conversation->refresh()->is_archived)->toBeTruthy();
})->with([
    'restore' => ['PATCH', '/restore'],
    'delete' => ['DELETE', ''],
]);

it('refuses to delete a conversation that is not archived', function () {
    $conversation = archivedConversation($this->user, $this->project, ['is_archived' => false]);

    $this->actingAs($this->user)
        ->deleteJson("/projects/{$this->project->id}/settings/archived-conversations/{$conversation->id}")
        ->assertNotFound();

    expect(Conversation::query()->whereKey($conversation->id)->exists())->toBeTrue();
});

it('refuses the archives of a project of another user', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->user)
        ->getJson("/projects/{$project->id}/settings/archived-conversations")
        ->assertForbidden();
});
