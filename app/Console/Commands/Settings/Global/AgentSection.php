<?php

namespace App\Console\Commands\Settings\Global;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Ai\Enums\SchemaChangeApplyModeEnum;
use App\Console\Commands\Settings\UserSection;
use App\Http\Requests\Settings\AgentSettingsRequest;
use App\Models\User;
use App\Services\Settings\SettingsManager;
use App\Services\Settings\UserSettings;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * How the agent behaves by default in every new conversation.
 */
class AgentSection extends UserSection
{
    public function __construct(protected SettingsManager $settings) {}

    public function label(): string
    {
        return __('settings.nav.agent');
    }

    public function run(User $user): void
    {
        $current = $user->settings;

        $request = $this->validate(AgentSettingsRequest::class, [
            'accessMode' => select(
                __('settings.agent.access_mode'),
                $this->enumOptions(AgentAccessModeEnum::cases(), 'ai.access_modes'),
                default: $current->agentAccessMode->value,
                hint: __('settings.agent.access_mode_description'),
            ),
            'effort' => select(
                __('settings.agent.effort'),
                $this->enumOptions(AgentEffortEnum::cases(), 'ai.efforts'),
                default: $current->agentEffort->value,
                hint: __('settings.agent.effort_description'),
            ),
            'schemaApplyMode' => select(
                __('settings.agent.schema_apply_mode'),
                $this->enumOptions(SchemaChangeApplyModeEnum::cases(), 'erd.assistant.mode'),
                default: $current->schemaApplyMode->value,
            ),
            'maxSteps' => text(
                __('settings.agent.max_steps'),
                default: (string) $current->agentMaxSteps,
                required: true,
                hint: __('settings.agent.max_steps_description', ['min' => UserSettings::MIN_AGENT_STEPS, 'max' => UserSettings::MAX_AGENT_STEPS]),
            ),
            'maxRows' => text(
                __('settings.agent.max_rows'),
                default: (string) $current->agentMaxRows,
                required: true,
                hint: __('settings.agent.max_rows_description', ['min' => UserSettings::MIN_AGENT_ROWS, 'max' => UserSettings::MAX_AGENT_ROWS]),
            ),
        ]);

        if ($request === null) {
            return;
        }

        $this->settings->update($user, 'agent', $request->validated());

        info(__('settings.messages.agent_saved'));
    }
}
