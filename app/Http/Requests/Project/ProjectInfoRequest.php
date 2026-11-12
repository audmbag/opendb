<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectInfoRequest extends FormRequest
{
    /**
     * A new project may be created with the CREATE PROJECT privilege, an existing one only changed by who manages it.
     */
    public function authorize(): bool
    {
        $project = $this->route('project') ?? ($this->filled('id') ? Project::find($this->input('id')) : null);

        return $project instanceof Project
            ? $this->user()->can('update', $project)
            : $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'ulid'],
            'name' => ['required', 'string', Rule::unique('projects', 'name')->ignore($this->input('id'))],
            'description' => 'required|string',
            'path' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_dir($value) && ! is_writable($value)) {
                    return $fail(__('requests.writable_directory'));
                }
            }],
        ];
    }
}
