<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Console\Commands\Chat\Output\SqlHighlighter;
use App\Console\Commands\Chat\Output\Theme;
use App\Console\Commands\Chat\Tools\SqlToolRenderer;
use App\Models\Project;
use App\Models\Provider;
use App\Models\User;
use App\Support\TranslatableMessage;
use Illuminate\Support\Str;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

it('refuses to start the chat with wrong credentials', function () {
    $user = User::factory()->create();

    $this->artisan('app:chat', ['--name' => $user->name])
        ->expectsQuestion('Mot de passe', 'wrong-password')
        ->expectsOutputToContain('Identifiants incorrects.')
        ->assertFailed();
});

it('does not start the chat on the project of another user', function () {
    $user = User::factory()->create();
    Project::factory()->create();
    Project::factory()->for($user)->draft()->create();

    $this->artisan('app:chat', ['--name' => $user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain('Aucun projet')
        ->assertFailed();
});

it('does not start the chat without an active model', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    Provider::query()->update(['api_key' => null]);

    $this->artisan('app:chat', ['--name' => $user->name])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsChoice('Projet', $project->id, [$project->id => $project->name])
        ->expectsOutputToContain('Aucun modèle actif')
        ->assertFailed();
});

it('sends the message as it was typed and renders the answer', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    SQLDatabaseAgent::fake(['Il y a **trois** clients.']);
    AnonymousAgent::fake(['Nombre de clients']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Combien de Clients ?')
        ->expectsOutputToContain('Il y a trois clients.')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Combien de Clients ?');
});

it('saves the conversation in the project with a title, as the web chat does', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    SQLDatabaseAgent::fake(['Bonjour !']);
    AnonymousAgent::fake(['Salutations']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Salut')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    $conversation = $user->conversations()->sole();
    expect($conversation)
        ->project_id->toBe($project->id)
        ->title->toBe('Salutations')
        ->and($conversation->messages()->oldest()->orderBy('id')->pluck('content', 'role')->all())->toBe([
            'user' => 'Salut',
            'assistant' => 'Bonjour !',
        ]);
});

it('keeps the agent in the access mode of the user until they change it', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    SQLDatabaseAgent::fake(['Un.', 'Deux.']);
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Première question')
        ->expectsQuestion('Message', '/mode read-write')
        ->expectsOutputToContain('Lecture-écriture')
        ->expectsQuestion('Message', 'Seconde question')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Première question'
        && $prompt->agent->accessMode === AgentAccessModeEnum::ReadOnly);
    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Seconde question'
        && $prompt->agent->accessMode === AgentAccessModeEnum::ReadWrite);
});

it('sends the approval of the user under the id of the tool call', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $approval = new PendingApproval('call_delete', 'WriteQuery', ['query' => 'DELETE FROM orders WHERE customer_id = 1', 'bindings' => []], new TranslatableMessage('ai.approvals.write_query.delete')->toJson());
    SQLDatabaseAgent::fake([AgentResponse::fakeWithPendingApprovals([$approval]), 'Les commandes sont supprimées.']);
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Supprime les commandes d\'Ada')
        ->expectsOutputToContain('DELETE FROM orders WHERE customer_id = 1')
        ->expectsOutputToContain('2 lignes affectées')
        ->expectsOutputToContain('Cette requête supprime définitivement des lignes')
        ->expectsQuestion('Approbation requise', 'approve')
        ->expectsOutputToContain('Les commandes sont supprimées.')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->approvalDecisions?->get('call_delete')?->isApproved() === true);
});

it('sends the rejection of the user with their reason', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $approval = new PendingApproval('call_delete', 'WriteQuery', ['query' => 'DELETE FROM orders', 'bindings' => []]);
    SQLDatabaseAgent::fake([AgentResponse::fakeWithPendingApprovals([$approval]), 'Rien n\'a été supprimé.']);
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Supprime toutes les commandes')
        ->expectsQuestion('Approbation requise', 'reject')
        ->expectsQuestion('Motif du rejet', 'Seulement celles de 2020')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    SQLDatabaseAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $decision = $prompt->approvalDecisions?->get('call_delete');

        return $decision?->isRejected() === true && $decision->result === 'Seulement celles de 2020';
    });
});

it('reports a command that does not exist', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    SQLDatabaseAgent::fake()->preventStrayPrompts();

    openChat($user, $project)
        ->expectsQuestion('Message', '/nope')
        ->expectsOutputToContain('Commande inconnue : /nope')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
});

it('comes back to a conversation and answers its last message again', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    SQLDatabaseAgent::fake(['Première réponse.', 'Seconde réponse.']);
    AnonymousAgent::fake(['Titre']);
    openChat($user, $project)
        ->expectsQuestion('Message', 'Combien de clients ?')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
    $conversation = $user->conversations()->sole();

    openChat($user, $project, ['--conversation' => $conversation->id])
        ->expectsOutputToContain('Première réponse.')
        ->expectsQuestion('Message', '/retry')
        ->expectsOutputToContain('Seconde réponse.')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    expect($conversation->messages()->oldest()->orderBy('id')->pluck('content')->all())->toBe(['Combien de clients ?', 'Seconde réponse.']);
});

it('does not open the conversation of another user', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $conversation = User::factory()->create()->conversations()->create(['id' => (string) Str::uuid(), 'project_id' => $project->id]);

    openChat($user, $project, ['--conversation' => $conversation->id])
        ->expectsOutputToContain('Aucune de vos conversations')
        ->assertFailed();
});

it('answers a question given with the command in read-only mode, then leaves', function () {
    $user = User::factory()->create();
    $user->update(['settings' => $user->settings->withSection('agent', ['accessMode' => AgentAccessModeEnum::ReadWrite->value])]);
    $project = chatProject($user);
    SQLDatabaseAgent::fake(['Trois clients.']);
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project, ['message' => 'Combien de clients ?'])
        ->expectsOutputToContain('Trois clients.')
        ->assertSuccessful();

    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->accessMode === AgentAccessModeEnum::ReadOnly);
});

it('tells why the provider ended the answer', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    SQLDatabaseAgent::fake([fn () => throw new RateLimitedException('Too many requests')]);
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Salut')
        ->expectsOutputToContain('Le fournisseur d\'IA reçoit trop de requêtes pour le moment.')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
});

it('asks again the approval a conversation was left waiting for', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $conversation = conversationWithToolCall($user, $project, [
        'id' => 'call_delete',
        'name' => 'WriteQuery',
        'arguments' => ['query' => 'DELETE FROM orders WHERE id = 3', 'bindings' => []],
        'approval_reason' => 'À confirmer.',
    ]);
    SQLDatabaseAgent::fake(['La commande est supprimée.']);

    openChat($user, $project, ['--conversation' => $conversation->id])
        ->expectsOutputToContain('DELETE FROM orders WHERE id = 3')
        ->expectsOutputToContain('1 ligne affectée')
        ->expectsQuestion('Approbation requise', 'approve')
        ->expectsOutputToContain('La commande est supprimée.')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->approvalDecisions?->get('call_delete')?->isApproved() === true);
});

it('sends again a message whose answer never came', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $attempts = 0;
    SQLDatabaseAgent::fake(function () use (&$attempts): string {
        return $attempts++ === 0 ? throw new RateLimitedException('Too many requests') : 'Bonjour !';
    });
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Salut')
        ->expectsOutputToContain('Le fournisseur d\'IA reçoit trop de requêtes pour le moment.')
        ->expectsQuestion('Message', '/retry')
        ->expectsOutputToContain('Bonjour !')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    expect($user->conversations()->sole()->messages()->oldest()->orderBy('id')->pluck('content')->all())->toBe(['Salut', 'Bonjour !']);
});

it('tells how to come back to the conversation when the chat is left', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    SQLDatabaseAgent::fake(['Bonjour !']);
    AnonymousAgent::fake(['Salutations']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Salut')
        ->expectsOutputToContain('Conversation : Salutations')
        ->expectsQuestion('Message', '/quit')
        ->expectsOutputToContain("chat --name='{$user->name}' --conversation=")
        ->assertSuccessful();
});

it('tells how many rows a read query returned and leaves the rows to the answer', function () {
    $theme = new Theme(decorated: false);
    $renderer = new SqlToolRenderer($theme, new SqlHighlighter($theme));
    $result = json_encode([['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Linus']]);

    expect($renderer->summary($result))->toBe('2 lignes')
        ->and($renderer->details($result))->toBe([]);
});

it('tells how to open the chat on the project again when no conversation was started', function () {
    $user = User::factory()->create();
    $project = chatProject($user);

    openChat($user, $project)
        ->expectsQuestion('Message', '/quit')
        ->expectsOutputToContain("chat --name='{$user->name}' --project={$project->id}")
        ->assertSuccessful();
});

it('comes back to a conversation without being told its project, and shows the tool calls of the agent again', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $conversation = conversationWithToolCall($user, $project, [
        'id' => 'call_read',
        'name' => 'ReadQuery',
        'arguments' => ['query' => 'SELECT name FROM customers', 'bindings' => []],
    ], MessageStatus::Completed);

    $this->artisan('chat', ['--name' => $user->name, '--conversation' => $conversation->id])
        ->expectsQuestion('Mot de passe', 'password')
        ->expectsOutputToContain('ReadQuery')
        ->expectsOutputToContain('SELECT name FROM customers')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
});
