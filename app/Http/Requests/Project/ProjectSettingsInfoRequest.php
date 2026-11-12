<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The name, the description and the working directory of an existing project.
 */
class ProjectSettingsInfoRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('projects', 'name')->ignore($project->id)],
            'description' => ['required', 'string'],
            'path' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_dir($value) || ! is_writable($value)) {
                    $fail(__('requests.writable_directory'));
                }
            }],
        ];
    }
}
