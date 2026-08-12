<?php

namespace App\Ai\Enums;

enum AgentAccessModeEnum: string
{
    case ReadOnly = 'read-only';

    case ReadWrite = 'read-write';

    public function label(): string
    {
        return match ($this) {
            self::ReadOnly => 'Lecture seule',
            self::ReadWrite => 'Lecture-écriture',
        };
    }

    public function instructionsView(): string
    {
        return "agent.instructions.modes.{$this->value}";
    }

    public function allowsWrites(): bool
    {
        return $this !== self::ReadOnly;
    }

    public function allowsCommandExecution(): bool
    {
        return $this === self::Full;
    }
}
