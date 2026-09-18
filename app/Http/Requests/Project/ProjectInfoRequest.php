<?php

namespace App\Http\Requests\Project;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'ulid'],
            'name' => ['required', 'string', Rule::unique('projects', 'name')->ignore($this->input('id'))],
            'description' => 'required|string',
            'path' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_dir($value) && ! is_writable($value)) {
                    return $fail('Le chemin doit être un répertoire existant et accessible en écriture.');
                }
            }],
        ];
    }
}
