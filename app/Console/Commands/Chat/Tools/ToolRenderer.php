<?php

namespace App\Console\Commands\Chat\Tools;

use Illuminate\Support\Str;

/**
 * How a call of a tool of the agent is shown in the chat: what it was asked, then what it answered. This one shows any
 * tool in a few words; a tool worth more, like a query, has its own renderer.
 */
class ToolRenderer
{
    /**
     * A longer argument or answer is cut: the user reads what the agent did, not all the data it read.
     */
    protected const int MAX_LENGTH = 160;

    /**
     * What the tool was asked, written under its name.
     *
     * @param  array<string, mixed>  $arguments
     * @return list<string>
     */
    public function call(array $arguments): array
    {
        return collect($arguments)
            ->reject(fn (mixed $value): bool => $value === null || $value === [] || $value === '')
            ->map(fn (mixed $value, string $name): string => "{$name}: ".$this->short($value))
            ->values()
            ->all();
    }

    /**
     * The answer of the tool in a few words, written next to its status.
     */
    public function summary(mixed $result): ?string
    {
        $decoded = $this->decoded($result);

        if (is_array($decoded) && array_key_exists('success', $decoded)) {
            return null;
        }

        return blank($result) ? null : $this->short($result);
    }

    /**
     * More of the answer of the tool, written under its status.
     *
     * @param  array<string, mixed>  $arguments  what the tool was asked
     * @return list<string>
     */
    public function details(mixed $result, array $arguments = []): array
    {
        return [];
    }

    /**
     * The error a tool reports in its answer instead of failing, like the tools whose output is displayed.
     */
    public function error(mixed $result): ?string
    {
        $decoded = $this->decoded($result);

        return is_array($decoded) && ($decoded['success'] ?? null) === false
            ? (string) ($decoded['error'] ?? '')
            : null;
    }

    protected function decoded(mixed $result): mixed
    {
        return is_string($result) ? json_decode($result, true) : $result;
    }

    protected function short(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Str::limit(Str::squish($text), self::MAX_LENGTH, '…');
    }
}
