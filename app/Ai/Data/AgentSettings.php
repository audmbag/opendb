<?php

namespace App\Ai\Data;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Models\Provider;
use App\Models\User;

/**
 * What the user chose for the answers of the agent: the model writing them, what it may change and how hard it thinks.
 * The application has no default model, so the provider and the model are always chosen by the user.
 */
final readonly class AgentSettings
{
    public function __construct(
        public Provider $provider,
        public string $model,
        public AgentAccessModeEnum $accessMode = AgentAccessModeEnum::ReadOnly,
        public AgentEffortEnum $effort = AgentEffortEnum::Default,
    ) {}

    /**
     * The given model, with the access mode and the effort the user saved in their settings.
     */
    public static function fromUser(User $user, Provider $provider, string $model): self
    {
        return new self($provider, $model, $user->settings->agentAccessMode, $user->settings->agentEffort);
    }

    public function withModel(Provider $provider, string $model): self
    {
        return clone ($this, ['provider' => $provider, 'model' => $model]);
    }

    public function withAccessMode(AgentAccessModeEnum $accessMode): self
    {
        return clone ($this, ['accessMode' => $accessMode]);
    }

    public function withEffort(AgentEffortEnum $effort): self
    {
        return clone ($this, ['effort' => $effort]);
    }
}
