<?php

namespace App\Http\Requests\Project;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The tables the agent of the project may no longer see.
 */
class ProjectAgentTablesRequest extends FormRequest
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
            'agentDisabled' => ['present', 'array'],
            'agentDisabled.*' => ['required', 'string', 'distinct'],
        ];
    }
}
