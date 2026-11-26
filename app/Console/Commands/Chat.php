<?php

namespace App\Console\Commands;

use App\Ai\Data\AgentSettings;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Console\Commands\Chat\ChatLoop;
use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\ModelChooser;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Output\Theme;
use App\Console\Commands\Chat\Turn\TurnRunner;
use App\Console\Commands\Concerns\AuthenticatesUser;
use App\Console\Commands\Concerns\ChoosesProject;
use App\Console\Commands\Concerns\TranslatesCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

#[Signature('chat
    {message? : console.commands.chat.message}
    {--name= : console.options.name}
    {--project= : console.commands.chat.project}
    {--model= : console.commands.chat.model}
    {--conversation= : console.commands.chat.conversation}', aliases: ['app:chat'])]
#[Description('console.commands.chat.description')]
class Chat extends Command
{
    use AuthenticatesUser, ChoosesProject, TranslatesCommand;

    public function handle(ModelChooser $modelChooser): int
    {
        $user = $this->authenticateUser();

        if ($user === null) {
            return self::FAILURE;
        }

        Auth::setUser($user);

        $conversation = null;

        if (($conversationId = $this->option('conversation')) !== null) {
            $conversation = $user->conversations()->find($conversationId);

            if ($conversation === null) {
                $this->error(__('chat_cli.errors.unknown_conversation', ['conversation' => $conversationId]));

                return self::FAILURE;
            }
        }

        // A conversation belongs to a project: coming back to it needs no other word about the project.
        $project = $this->chooseProject($user, $this->option('project') ?? $conversation?->project_id);

        if ($project === null) {
            return self::FAILURE;
        }

        if ($conversation !== null && $conversation->project_id !== $project->id) {
            $this->error(__('chat_cli.errors.unknown_conversation', ['conversation' => $conversationId]));

            return self::FAILURE;
        }

        if (! $modelChooser->hasModels()) {
            $this->error(__('console.no_model'));

            return self::FAILURE;
        }

        $model = $modelChooser->choose($this->option('model'));

        if ($model === null) {
            $this->error(__('chat_cli.errors.unknown_model', ['model' => $this->option('model')]));

            return self::FAILURE;
        }

        $message = $this->argument('message');
        $settings = AgentSettings::fromUser($user, ...$model);

        // The agent never does more than the user: without a privilege changing the database, it only reads.
        if ($settings->accessMode->allowsWrites() && $user->cannot('writeWithAgent', $project)) {
            $settings = $settings->withAccessMode(AgentAccessModeEnum::ReadOnly);
        }

        $session = new ChatSession($user, $project, $settings, interactive: $message === null);

        $session->conversation = $conversation;
        // A conversation left without a title, by an older version, gets one with its next answer.
        $session->awaitsTitle = $conversation !== null && blank($conversation->title);

        $this->shareTerminal();
        $this->laravel->instance(ChatSession::class, $session);

        if ($message === null) {
            $this->laravel->make(ChatLoop::class)->run($session);

            return self::SUCCESS;
        }

        // Nobody is there to approve a change: a question asked with the command can only read the database.
        $session->settings = $session->settings->withAccessMode(AgentAccessModeEnum::ReadOnly);

        $this->laravel->make(TurnRunner::class)->send($session, $message);

        return self::SUCCESS;
    }

    /**
     * Every class of the chat writes to the output of this command, in its colors.
     */
    private function shareTerminal(): void
    {
        $terminal = new Terminal($this->output);

        $this->laravel->instance(Terminal::class, $terminal);
        $this->laravel->instance(Theme::class, $terminal->theme);
    }
}
