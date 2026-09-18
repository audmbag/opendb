<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\DriverConfigRequest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'description' => 'required|string',
            'path' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_dir($value) && ! is_writable($value)) {
                    return $fail('Le chemin doit être un répertoire existant et accessible en écriture.');
                }
            }],
            ...DriverConfigRequest::getValidationRules($this->request),
        ];
    }
}
