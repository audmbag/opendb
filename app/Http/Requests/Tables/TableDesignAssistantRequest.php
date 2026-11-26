<?php

namespace App\Http\Requests\Tables;

use App\Http\Requests\Concerns\SelectsAiModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the user asks the assistant of the designer of a new table, with what its form holds, and the provider and
 * the model chosen by the user.
 */
class TableDesignAssistantRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:2000'],
            'form' => ['nullable', 'array'],
            'form.name' => ['nullable', 'string', 'max:255'],
            'form.columns' => ['nullable', 'array', 'max:500'],
            'form.primaryKey' => ['nullable', 'array'],
            'form.indexes' => ['nullable', 'array', 'max:200'],
            'form.foreignKeys' => ['nullable', 'array', 'max:200'],
        ];
    }

    /**
     * What the form holds, read by the agent only: the proposal it makes is checked on its own.
     *
     * @return array<string, mixed>
     */
    public function form(): array
    {
        return array_intersect_key((array) $this->input('form', []), array_flip(['name', 'columns', 'primaryKey', 'indexes', 'foreignKeys']));
    }
}
