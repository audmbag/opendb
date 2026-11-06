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
 */
#[DeleteWhenMissingModels]
class GenerateConversationTitle implements ShouldQueue
{
    use Queueable;

    /**
     * The title falls back to the message when the provider fails: a second attempt would only delay it.
     */
    public int $tries = 1;

    public function __construct(
        public Conversation $conversation,
        public string $message,
        public Lab $provider,
        public string $model,
    ) {}

    public function handle(): void
    {
        $this->conversation->update([
            'title' => ProviderManagerService::for(Provider::findOrFail($this->provider->value))->generateConversationTitle($this->message, $this->model),
        ]);
    }

    /**
     * The conversation still gets a title, so the page waiting for it stops waiting.
     */
    public function failed(?Throwable $exception): void
    {
        $this->conversation->update(['title' => Str::limit($this->message, 100, preserveWords: true)]);
    }
}
