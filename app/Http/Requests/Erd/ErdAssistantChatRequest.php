<?php

namespace App\Http\Requests\Erd;

use App\Http\Requests\Concerns\SelectsAiModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A message to the assistant of the diagram, sent by the useChat hook of its panel with the messages exchanged so
 * far, and the provider and the model chosen by the user.
 */
class ErdAssistantChatRequest extends FormRequest
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
            'messages' => ['required', 'array', 'min:1', 'max:60'],
            'messages.*.id' => ['required', 'string'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.parts' => ['present', 'array'],
        ];
    }
}
