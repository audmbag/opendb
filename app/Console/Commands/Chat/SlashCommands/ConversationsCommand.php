<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Turn\TranscriptPrinter;
use App\Console\Commands\Chat\Turn\TurnRunner;
use App\Console\Commands\Settings\Section;
use App\Repository\ConversationRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;

use function Laravel\Prompts\select;

/**
 * Lists the conversations of the project, or those matching a search, and comes back to the chosen one.
 */
class ConversationsCommand extends SlashCommand
{
    private const int TITLE_LENGTH = 60;

    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly TranscriptPrinter $transcript,
        private readonly TurnRunner $turns,
        private readonly Terminal $terminal,
    ) {}

    public function name(): string
    {
        return 'conversations';
    }

    public function run(ChatSession $session, string $arguments): void
    {
        $conversations = $this->conversations($session, $arguments);

        if ($conversations->isEmpty()) {
            $this->terminal->notice($arguments === '' ? __('conversations.empty_history') : __('conversations.no_match'));

            return;
        }

        $chosen = select(__('conversations.recent'), [...$conversations->all(), Section::BACK => __('console.back')], scroll: 10);

        if ($chosen === Section::BACK) {
            return;
        }

        $session->conversation = $session->user->conversations()->findOrFail($chosen);

        $this->transcript->print($session->conversation);
        $this->turns->resume($session);
    }

    /**
     * @return Collection<string, string> the title of each conversation, by its id
     */
    private function conversations(ChatSession $session, string $search): Collection
    {
        if ($search !== '') {
            return $this->conversationRepository->search($session->project, $search)->mapWithKeys(
                fn (array $conversation): array => [$conversation['id'] => $this->title($conversation['title'])]
            );
        }

        return $this->conversationRepository->get($session->project)->mapWithKeys(
            fn (Conversation $conversation): array => [$conversation->id => $this->title($conversation->title).' · '.$conversation->updated_at?->diffForHumans()]
        );
    }

    private function title(?string $title): string
    {
        return Str::limit($title ?? __('conversations.untitled'), self::TITLE_LENGTH, '…');
    }
}
