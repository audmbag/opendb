<?php

namespace App\Ai\Enums;

use App\Services\Erd\Assistant\SchemaChangePlan;

/**
 * When the changes of structure asked by the assistant of the diagram run: once the user applies the draft drawn
 * in the diagram, at once for what only adds to the structure, or always at once.
 */
enum SchemaChangeApplyModeEnum: string
{
    case Draft = 'draft';

    case Additions = 'additions';

    case Auto = 'auto';

    public function requiresApproval(SchemaChangePlan $plan): bool
    {
        return match ($this) {
            self::Draft => true,
            self::Additions => $plan->isDestructive(),
            self::Auto => false,
        };
    }
}
