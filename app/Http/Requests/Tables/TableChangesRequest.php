<?php

namespace App\Http\Requests\Tables;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The changes waiting in the grid, applied together. Their values are checked against the columns of the table afterwards.
 */
class TableChangesRequest extends FormRequest
{
    public const int MAX_CHANGES = 500;

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
            'changes' => ['required', 'array', 'list', 'max:'.self::MAX_CHANGES],
            'changes.*.type' => ['required', Rule::in(['insert', 'update', 'delete'])],
            'changes.*.key' => ['array'],
            'changes.*.values' => ['array'],
        ];
    }

    /**
     * @return list<array{type: 'insert'|'update'|'delete', key?: array<string, mixed>, values?: array<string, mixed>}>
     */
    public function changes(): array
    {
        return $this->validated('changes');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'changes.required' => 'Aucune modification à appliquer.',
            'changes.max' => 'Impossible d\'appliquer plus de '.self::MAX_CHANGES.' modifications à la fois.',
        ];
    }
}
