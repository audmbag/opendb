<?php

namespace App\Http\Requests\Tables;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A column of a table and the one it is moved after, none to make it the first one.
 */
class ColumnMoveRequest extends FormRequest
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
            'column' => ['required', 'string'],
            'after' => ['present', 'nullable', 'string'],
        ];
    }

    public function movedColumn(): string
    {
        return $this->validated('column');
    }

    public function previousColumn(): ?string
    {
        return $this->validated('after');
    }
}
