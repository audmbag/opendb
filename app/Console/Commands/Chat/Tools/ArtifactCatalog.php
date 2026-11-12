<?php

namespace App\Console\Commands\Chat\Tools;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use Illuminate\Support\Collection;
use Laravel\Ai\Models\ConversationMessage;

use function Laravel\Prompts\select;

/**
 * The artifacts of a conversation, in the order the agent displayed them. They are read from the conversation itself,
 * so those of a conversation the user comes back to can be opened too.
 */
class ArtifactCatalog
{
    public function __construct(
        private readonly ToolRendererRegistry $toolRenderers,
        private readonly Terminal $terminal,
    ) {}

    /**
     * @param  class-string|null  $tool  only the artifacts of this tool
     * @return Collection<int, Artifact>
     */
    public function all(ChatSession $session, ?string $tool = null): Collection
    {
        if ($session->conversation === null) {
            return new Collection;
        }

        return $session->conversation->messages()->where('role', 'assistant')->oldest()->orderBy('id')->get()
            ->flatMap(fn (ConversationMessage $message): array => $message->tool_calls ?? [])
            ->map(fn (array $call): array => [...$call, 'renderer' => $this->toolRenderers->for((string) $call['name'])])
            ->filter(fn (array $call): bool => $call['renderer'] instanceof OpensArtifact
                && isset($call['result'])
                && ! ($call['denied'] ?? false)
                && $call['renderer']->error($call['result']) === null)
            ->values()
            ->map(fn (array $call, int $position): Artifact => new Artifact($position + 1, $call['name'], (array) ($call['arguments'] ?? []), $call['result'], $call['renderer']))
            ->when($tool !== null, fn (Collection $artifacts) => $artifacts->filter(fn (Artifact $artifact): bool => $artifact->tool === class_basename($tool))->values());
    }

    /**
     * The artifact the user names by its number, or picks in the list; the only one is taken without asking.
     *
     * @param  class-string|null  $tool
     * @return Artifact|null null, once the reason is written, when there is none to give
     */
    public function pick(ChatSession $session, string $number, ?string $tool = null): ?Artifact
    {
        $artifacts = $this->all($session, $tool);

        if ($artifacts->isEmpty()) {
            $this->terminal->failure(__('ai.artifacts.empty'));

            return null;
        }

        if ($number !== '') {
            $artifact = $artifacts->first(fn (Artifact $artifact): bool => (string) $artifact->number === $number);

            if ($artifact === null) {
                $this->terminal->failure(__('chat_cli.errors.unknown_artifact', ['number' => $number, 'numbers' => $artifacts->map->number->implode(', ')]));
            }

            return $artifact;
        }

        if ($artifacts->count() === 1 || ! $session->interactive) {
            return $artifacts->last();
        }

        $chosen = select(
            __('ai.artifacts.list_title', ['count' => $artifacts->count()]),
            $artifacts->mapWithKeys(fn (Artifact $artifact): array => [$artifact->number => $artifact->label()])->all(),
            default: $artifacts->last()->number,
            scroll: 10,
        );

        return $artifacts->first(fn (Artifact $artifact): bool => $artifact->number === (int) $chosen);
    }
}
