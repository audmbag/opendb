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
        return 'Modifie la mémoire persistante du projet, partagée entre toutes les conversations de ce projet. '
            .'Le paramètre `action` vaut `add` (ajouter une entrée avec `note`), `replace` (remplacer l\'entrée '
            .'contenant `match` par `note`) ou `remove` (supprimer l\'entrée contenant `match`). '
            .'`match` est un extrait du texte d\'une entrée existante, qui doit en désigner une seule.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $request->enum('action', MemoryActionEnum::class);
        $note = $request->string('note')->trim()->toString();
        $match = $request->string('match')->trim()->toString();

        if ($action === null) {
            return 'Action inconnue : utilise `add`, `replace` ou `remove`.';
        }

        if ($action->requiresNote() && $note === '') {
            return "Le paramètre `note` est obligatoire pour l'action `{$action->value}`.";
        }

        if ($action->requiresMatch() && $match === '') {
            return "Le paramètre `match` est obligatoire pour l'action `{$action->value}`.";
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

        return "Mémoire du projet mise à jour (`{$action->value}`).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(array_column(MemoryActionEnum::cases(), 'value'))
                ->required()
                ->description("L'opération à effectuer sur la mémoire : add, replace ou remove."),
            'note' => $schema->string()
                ->description('Le texte de l\'entrée, une seule phrase concise (requis pour add et replace). '
                    .'Une consigne de l\'utilisateur s\'écrit en règle impérative qui garde toutes ses conditions.'),
            'match' => $schema->string()
                ->description('Un extrait du texte de l\'entrée existante à cibler (requis pour replace et remove).'),
        ];
    }
}
