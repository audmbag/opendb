<?php

namespace App\Ai\Services;

use Illuminate\Contracts\Cache\Repository;

/**
 * The answers of the agent the user stopped. The request streaming an answer runs in another process than the one
 * asking to stop it, so the request is shared through the cache, and read by the agent before each of its steps.
 */
class AnswerCancellation
{
    /**
     * Long enough for the step running when the user stops the answer to end.
     */
    protected const int TTL_SECONDS = 600;

    public function __construct(protected Repository $cache) {}

    public function request(string $conversationId): void
    {
        $this->cache->put($this->key($conversationId), true, self::TTL_SECONDS);
    }

    public function isRequested(string $conversationId): bool
    {
        return (bool) $this->cache->get($this->key($conversationId), false);
    }

    public function forget(string $conversationId): void
    {
        $this->cache->forget($this->key($conversationId));
    }

    protected function key(string $conversationId): string
    {
        return "conversation:{$conversationId}:cancelled";
    }
}
