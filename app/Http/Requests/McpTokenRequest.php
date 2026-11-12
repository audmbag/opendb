<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class McpTokenRequest extends FormRequest
{
    /**
     * Any user may issue a token, on a project they may open: the gateway checks the project.
     */
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
            'name' => ['required', 'string', 'max:120'],
            'project_id' => ['required', 'string', 'exists:projects,id'],
        ];
    }
}
