<?php

namespace App\Http\Requests\Tables;

use App\Http\Requests\Concerns\SelectsAiModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the user asks the agent to write in the console, with what the console already holds,
 * and the provider and the model chosen by the user.
 */
class QueryWriterRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:1000'],
            'script' => ['nullable', 'string', 'max:1000000'],
        ];
    }
}
