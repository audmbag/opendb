<?php

namespace App\Ai\Tools\Memory;

use App\Ai\Enums\MemoryActionEnum;
use App\Services\ProjectMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class UpdateProjectMemory implements Tool
{
    public function __construct(
        public ProjectMemory $memory,
    ) {}

    public function description(): Stringable|string
    {
        return 'Changes the persistent memory of the project, shared between all the conversations of this project. '
            .'The `action` parameter is `add` (add an entry with `note`), `replace` (replace the entry '
            .'containing `match` with `note`) or `remove` (remove the entry containing `match`). '
            .'`match` is an extract of the text of an existing entry, which must point to a single one.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $request->enum('action', MemoryActionEnum::class);
        $note = $request->string('note')->trim()->toString();
        $match = $request->string('match')->trim()->toString();

        if ($action === null) {
            return 'Unknown action: use `add`, `replace` or `remove`.';
        }

        if ($action->requiresNote() && $note === '') {
            return "The `note` parameter is required for the `{$action->value}` action.";
        }

        if ($action->requiresMatch() && $match === '') {
            return "The `match` parameter is required for the `{$action->value}` action.";
        }

        try {
            match ($action) {
                MemoryActionEnum::Add => $this->memory->add($note),
                MemoryActionEnum::Replace => $this->memory->replace($match, $note),
                MemoryActionEnum::Remove => $this->memory->remove($match),
            };
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return "Project memory updated (`{$action->value}`).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(array_column(MemoryActionEnum::cases(), 'value'))
                ->required()
                ->description('The operation to run on the memory: add, replace or remove.'),
            'note' => $schema->string()
                ->description('The text of the entry, a single concise sentence (required for add and replace). '
                    .'An instruction of the user is written as an imperative rule that keeps all its conditions.'),
            'match' => $schema->string()
                ->description('An extract of the text of the existing entry to target (required for replace and remove).'),
        ];
    }
}
