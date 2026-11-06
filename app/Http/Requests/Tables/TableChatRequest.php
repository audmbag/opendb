<?php

namespace App\Http\Requests\Tables;

use App\Http\Requests\Concerns\SelectsAiModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A quick question about a table, sent by the useChat hook of its panel with the messages exchanged so far,
 * the view of the table the user is looking at, and the provider and the model chosen by the user.
 */
class TableChatRequest extends TableViewRequest
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
            'messages' => ['required', 'array', 'min:1', 'max:40'],
            'messages.*.id' => ['required', 'string'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.parts' => ['present', 'array'],
        ];
    }
}
