<?php

namespace App\Http\Requests\Tables;

use App\Http\Requests\Concerns\SelectsAiModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A question to the AI helpers of the Tables tab, with the provider and the model chosen by the user,
 * and the view of the table it is about.
 */
class TableAssistantRequest extends TableViewRequest
{
    use SelectsAiModel;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...$this->aiModelRules(),
            'prompt' => [Rule::requiredIf(fn (): bool => $this->routeIs('tables.ai.filter')), 'string', 'max:500'],
        ];
    }
}
