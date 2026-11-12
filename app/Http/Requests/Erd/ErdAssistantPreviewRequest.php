<?php

namespace App\Http\Requests\Erd;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The changes of structure asked by the assistant of the diagram, sent back by its panel to draw them as a draft.
 * Each change is checked against the database by the planner, which says what is wrong with it.
 */
class ErdAssistantPreviewRequest extends FormRequest
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
            'changes' => ['required', 'array', 'min:1', 'max:200'],
            'changes.*' => ['array'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function changes(): array
    {
        return array_values($this->input('changes'));
    }
}
