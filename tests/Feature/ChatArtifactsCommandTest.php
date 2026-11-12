<?php

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Tools\Database\CreateRecord;
use App\Models\User;
use App\Support\TranslatableMessage;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

/**
 * A conversation where the agent displayed the given artifact.
 *
 * @param  array<string, mixed>  $arguments
 * @param  array<string, mixed>  $result
 */
function chatConversationWithArtifact(User $user, $project, string $tool, array $arguments, array $result = [])
{
    return conversationWithToolCall($user, $project, [
        'id' => 'call_artifact',
        'name' => $tool,
        'arguments' => $arguments,
        'result' => json_encode(['success' => true, ...$result]),
    ], MessageStatus::Completed);
}

afterEach(fn () => putenv('COLUMNS'));

it('opens the rows of a table the agent displayed, page by page', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $conversation = chatConversationWithArtifact($user, $project, 'ShowQueryResults', ['query' => 'SELECT name, city FROM customers ORDER BY id', 'title' => 'Clients'], ['totalRows' => 3]);

    openChat($user, $project, ['--conversation' => $conversation->id])
        ->expectsQuestion('Message', '/open')
        ->expectsOutputToContain('Grace')
        ->expectsOutputToContain('Lignes 1 à 3 sur 3')
        ->expectsQuestion('Page 1 sur 1', 'close')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
});

it('exports a table the agent displayed to the exports directory of the user in the project, never to the current one', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $project->update(['path' => sys_get_temp_dir().'/chat-project-'.uniqid()]);
    $conversation = chatConversationWithArtifact($user, $project, 'ShowQueryResults', ['query' => 'SELECT name FROM customers ORDER BY id', 'title' => 'Clients'], ['totalRows' => 3]);
    $directory = sys_get_temp_dir().'/chat-csv-'.uniqid();
    mkdir($directory);
    $previousDirectory = getcwd();
    chdir($directory);

    try {
        openChat($user, $project, ['--conversation' => $conversation->id])
            ->expectsQuestion('Message', '/csv 1')
            ->expectsOutputToContain('3 lignes écrites dans')
            ->expectsQuestion('Message', '/quit')
            ->assertSuccessful();
    } finally {
        chdir($previousDirectory);
    }

    $exports = glob($project->workspaceOf($user)->path('exports').'/*.csv');

    expect(glob($directory.'/*'))->toBe([])
        ->and($exports)->toHaveCount(1)
        ->and(file_get_contents($exports[0]))->toBe("name\nAda\nGrace\nAlan\n");
});

it('draws the chart the agent displayed as bars', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $conversation = chatConversationWithArtifact($user, $project, 'ShowChart', [
        'query' => 'SELECT status, SUM(total) AS total FROM orders GROUP BY status ORDER BY status',
        'chartType' => 'bar',
        'xColumn' => 'status',
        'yColumns' => ['total'],
        'title' => 'Ventes par statut',
    ]);

    openChat($user, $project, ['--conversation' => $conversation->id])
        ->expectsQuestion('Message', '/open 1')
        ->expectsOutputToContain('Ventes par statut')
        ->expectsOutputToContain('█ 170')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
});

it('links the schema diagram to the web chat, where it is drawn', function () {
    putenv('COLUMNS=120');
    $user = User::factory()->create();
    $project = chatProject($user);
    $conversation = chatConversationWithArtifact($user, $project, 'ShowSchemaDiagram', ['focusedTable' => null], ['focusedTable' => null, 'displayedTables' => ['customers', 'orders']]);

    openChat($user, $project, ['--conversation' => $conversation->id])
        ->expectsOutputToContain('customers · orders')
        ->expectsOutputToContain(route('conversation.chat', ['project' => $project, 'conversation' => $conversation]))
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
});

it('shows again the preview of an artifact when its conversation is opened again, with the link to the web chat', function () {
    putenv('COLUMNS=120');
    $user = User::factory()->create();
    $project = chatProject($user);
    $conversation = chatConversationWithArtifact($user, $project, 'ShowQueryResults', ['query' => 'SELECT name, city FROM customers ORDER BY id', 'title' => 'Clients'], ['totalRows' => 3]);

    openChat($user, $project, ['--conversation' => $conversation->id])
        ->expectsOutputToContain('Grace')
        ->expectsOutputToContain(route('conversation.chat', ['project' => $project, 'conversation' => $conversation]))
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
});

it('tells which artifacts exist when the number given is not one of them', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $conversation = chatConversationWithArtifact($user, $project, 'ShowKpis', ['kpis' => [['label' => 'Clients', 'query' => 'SELECT COUNT(*) FROM customers', 'format' => 'number']]]);

    openChat($user, $project, ['--conversation' => $conversation->id])
        ->expectsQuestion('Message', '/open 7')
        ->expectsOutputToContain('Aucun artefact ne porte le numéro 7. Numéros possibles : 1.')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();
});

it('asks the columns of the record to create, then approves the call with what was typed', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $approval = new PendingApproval('call_create', 'CreateRecord', ['table' => 'customers', 'known_values' => ['name' => 'Linus']], new TranslatableMessage('ai.approvals.form_input')->toJson());
    SQLDatabaseAgent::fake([AgentResponse::fakeWithPendingApprovals([$approval]), 'Le client est créé.']);
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Ajoute le client Linus')
        ->expectsQuestion('Approbation requise', 'approve')
        ->expectsQuestion('name', 'Linus')
        ->expectsQuestion('city', 'Helsinki')
        ->expectsOutputToContain('Le client est créé.')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    expect(Cache::get(CreateRecord::submissionCacheKey('call_create')))->toBe(['table' => 'customers', 'values' => ['name' => 'Linus', 'city' => 'Helsinki']]);
    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->approvalDecisions?->get('call_create')?->isApproved() === true);
});

it('rejects the call when the user gives up a form they cannot fill', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $approval = new PendingApproval('call_create', 'CreateRecord', ['table' => 'customers'], new TranslatableMessage('ai.approvals.form_input')->toJson());
    SQLDatabaseAgent::fake([AgentResponse::fakeWithPendingApprovals([$approval]), 'Rien n\'a été créé.']);
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project)
        ->expectsQuestion('Message', 'Ajoute un client')
        ->expectsQuestion('Approbation requise', 'approve')
        ->expectsQuestion('name', '')
        ->expectsQuestion('city', '')
        ->expectsOutputToContain('name')
        ->expectsConfirmation('Corriger les valeurs ?', 'no')
        ->expectsOutputToContain('Formulaire abandonné')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->approvalDecisions?->get('call_create')?->isApproved() === false);
});

it('joins a file to the next message only', function () {
    $user = User::factory()->create();
    $project = chatProject($user);
    $file = tempnam(sys_get_temp_dir(), 'note').'.txt';
    file_put_contents($file, 'Bonjour');
    SQLDatabaseAgent::fake(['Lu.', 'Encore.']);
    AnonymousAgent::fake(['Titre']);

    openChat($user, $project)
        ->expectsQuestion('Message', '/attach /nowhere/missing.txt')
        ->expectsOutputToContain('Fichier introuvable ou illisible')
        ->expectsQuestion('Message', "/attach {$file}")
        ->expectsOutputToContain('1 fichier joint')
        ->expectsQuestion('Message', 'Lis ce fichier')
        ->expectsQuestion('Message', 'Et maintenant ?')
        ->expectsQuestion('Message', '/quit')
        ->assertSuccessful();

    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Lis ce fichier' && $prompt->attachments->count() === 1);
    SQLDatabaseAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Et maintenant ?' && $prompt->attachments->isEmpty());
});
