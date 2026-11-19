<?php

namespace App\Ai\Services;

use Illuminate\Contracts\Cache\Repository;

/**
 * The answers of the agent being streamed to the web chat. The page may stop reading an answer, when the user goes
 * to another page, while the agent goes on answering: what is still in progress is only known here.
 */
class AnswersInProgress
{
    /**
     * Longer than any answer: an answer whose request died without finishing is forgotten after it.
     */
    protected const int TTL_SECONDS = 3600;

    public function __construct(protected Repository $cache) {}

    public function start(string $conversationId): void
    {
        $this->cache->put($this->key($conversationId), true, self::TTL_SECONDS);
    }

    public function finish(string $conversationId): void
    {
        $this->cache->forget($this->key($conversationId));
    }

    /**
     * The conversations, among the given ones, whose answer is still in progress.
     *
     * @param  list<string>  $conversationIds
     * @return list<string>
     */
    public function among(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $keys = array_map($this->key(...), $conversationIds);

        return $this->cache->many($keys)
            |> (fn (array $values): array => array_filter(array_combine($conversationIds, array_values($values))))
            |> array_keys(...)
            |> array_values(...);
    }

    protected function key(string $conversationId): string
    {
        return "conversation:{$conversationId}:answering";
    }
}
