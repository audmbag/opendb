<?php

namespace App\Http\Requests\Settings;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Services\Settings\UserSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * How the agent behaves by default for the user.
 */
class AgentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accessMode' => ['required', Rule::enum(AgentAccessModeEnum::class)],
            'effort' => ['required', Rule::enum(AgentEffortEnum::class)],
            'maxSteps' => ['required', 'integer', 'between:'.UserSettings::MIN_AGENT_STEPS.','.UserSettings::MAX_AGENT_STEPS],
            'maxRows' => ['required', 'integer', 'between:'.UserSettings::MIN_AGENT_ROWS.','.UserSettings::MAX_AGENT_ROWS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'accessMode' => "mode d'accès",
            'effort' => 'effort',
            'maxSteps' => "nombre d'étapes",
            'maxRows' => 'nombre de lignes',
        ];
    }
}
