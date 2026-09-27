<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\DriverConfigRequest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveTableInfosRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'tables' => ['required', 'array'],
            'tables.*' => ['required', 'string'],
        ];
    }
}
