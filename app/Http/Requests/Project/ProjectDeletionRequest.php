<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The name of the project typed by the user to confirm its deletion.
 */
class ProjectDeletionRequest extends FormRequest
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
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', Rule::in([$project->name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Saisissez le nom du projet pour confirmer sa suppression.',
            'name.in' => 'Le nom saisi ne correspond pas au nom du projet.',
        ];
    }
}
