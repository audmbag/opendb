<?php

namespace App\Jobs;

use App\Ai\Services\ProviderManagerService;
use App\Models\Provider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Models\Conversation;
use Throwable;

/**
 * Title a new conversation with the model chosen by the user, away from the request that starts it:
 * the conversation opens at once, and its title shows as soon as the provider has answered.
 *
 * The conversation always ends up with a title, so the pages waiting for it stop waiting: the message stands for it
 * when the provider fails or answers nothing, as a model spending its whole answer on reasoning does.
 */
#[DeleteWhenMissingModels]
class GenerateConversationTitle implements ShouldQueue
{
    use Queueable;

    /**
     * A second attempt would only delay the title: the message stands for it at once.
     */
    public int $tries = 1;

    public function __construct(
        public Conversation $conversation,
        public string $message,
        public Lab|string $provider,
        public string $model,
    ) {}

    public function handle(): void
    {
        $this->conversation->update(['title' => $this->generate() ?? self::titleFromMessage($this->message)]);
    }

    /**
     * The conversation still gets a title when the job itself fails, killed by the worker for instance.
     */
    public function failed(?Throwable $exception): void
    {
        $this->conversation->update(['title' => self::titleFromMessage($this->message)]);
    }

    /**
     * The title a conversation bears when the model gives none: its first message, on one line.
     */
    public static function titleFromMessage(string $message): string
    {
        return Str::limit(Str::squish($message), 100, preserveWords: true);
    }

    /**
     * The title written by the model, null when it fails or answers nothing usable.
     */
    private function generate(): ?string
    {
        try {
            $provider = Provider::findOrFail($this->provider instanceof Lab ? $this->provider->value : $this->provider);

            $answer = ProviderManagerService::for($provider)->generateConversationTitle($this->message, $this->model);
        } catch (Throwable) {
            return null;
        }

        return self::cleanTitle($answer);
    }

    /**
     * Keep the first line the model wrote, without the quotes, the Markdown or the final dot it may add anyway.
     * A model sometimes goes on after the title, to correct itself for instance.
     */
    private static function cleanTitle(string $answer): ?string
    {
        $firstLine = Str::of($answer)
            ->explode("\n")
            ->map(fn (string $line): string => mb_trim(Str::squish($line), " \t\"'`*#_«»“”‘’.:;"))
            ->first(fn (string $line): bool => $line !== '');

        return $firstLine === null ? null : Str::limit($firstLine, 100, preserveWords: true);
    }
}
