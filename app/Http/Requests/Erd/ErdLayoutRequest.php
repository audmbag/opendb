<?php

namespace App\Http\Requests\Erd;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The place of every table moved in the diagram, sent whole. An empty list lets the diagram lay itself out again.
 */
class ErdLayoutRequest extends FormRequest
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
            'positions' => ['present', 'array', 'max:2000'],
            'positions.*.table' => ['required', 'string', 'max:255', 'distinct'],
            'positions.*.x' => ['required', 'numeric'],
            'positions.*.y' => ['required', 'numeric'],
        ];
    }
}
