<?php

namespace App\Http\Requests\SqlEditor;

use App\Ai\Agents\Tables\QueryReviewerAgent;
use App\Http\Requests\Concerns\SelectsAiModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A statement of the SQL editor to fix, explain or optimize, with the error the database gave, and the provider and
 * the model chosen by the user.
 */
class QueryReviewRequest extends FormRequest
{
    use SelectsAiModel;

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
            ...$this->aiModelRules(),
            'mode' => ['required', Rule::in(QueryReviewerAgent::MODES)],
            'sql' => ['required', 'string', 'max:100000'],
            'error' => ['nullable', 'required_if:mode,fix', 'string', 'max:5000'],
        ];
    }
}
