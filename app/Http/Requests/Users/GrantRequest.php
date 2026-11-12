<?php

namespace App\Http\Requests\Users;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\PrivilegeManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Privileges granted to or revoked from a user or a role, on the whole instance, on a project or on one of its tables.
 * Who sends it must hold them with the grant option, which the privileges of OpenDB check once the request is valid.
 */
class GrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PrivilegeManager::class)->for($this->user())->canGrantAny($this->input('project_id'), $this->input('table'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grantee_type' => ['required', Rule::in(['user', 'role'])],
            'grantee_id' => ['required', 'integer', Rule::exists($this->input('grantee_type') === 'role' ? 'roles' : 'users', 'id')],
            'privileges' => ['required', 'array', 'min:1'],
            'privileges.*' => [Rule::enum(PrivilegeEnum::class)],
            'project_id' => ['nullable', 'string', Rule::exists('projects', 'id')],
            'table' => ['nullable', 'string', 'max:255'],
            'grant_option' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('table') && ! $this->filled('project_id')) {
                $validator->errors()->add('table', __('privileges.errors.table_without_project'));

                return;
            }

            $grantee = $this->grantee();

            if ($grantee instanceof User && $grantee->is_root) {
                $validator->errors()->add('grantee_id', __('privileges.errors.root'));
            }

            foreach ($this->privileges() as $privilege) {
                if (! $privilege->allowsLevel($this->level())) {
                    $validator->errors()->add('privileges', __('privileges.errors.invalid_level', ['privilege' => $privilege->keyword(), 'level' => $this->level()->label()]));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'grantee_id' => __('attributes.grantee'),
            'privileges' => __('attributes.privileges'),
            'privileges.*' => __('attributes.privilege'),
            'project_id' => __('attributes.project'),
            'table' => __('attributes.table'),
        ];
    }

    public function grantee(): User|Role
    {
        return $this->input('grantee_type') === 'role'
            ? Role::query()->findOrFail($this->integer('grantee_id'))
            : User::query()->findOrFail($this->integer('grantee_id'));
    }

    /**
     * @return list<PrivilegeEnum>
     */
    public function privileges(): array
    {
        return array_values(array_unique(array_map(PrivilegeEnum::from(...), (array) $this->input('privileges', [])), SORT_REGULAR));
    }

    public function project(): ?Project
    {
        return $this->filled('project_id') ? Project::query()->findOrFail($this->input('project_id')) : null;
    }

    public function table(): ?string
    {
        return $this->filled('table') ? (string) $this->input('table') : null;
    }

    public function level(): PrivilegeLevelEnum
    {
        return match (true) {
            ! $this->filled('project_id') => PrivilegeLevelEnum::Global,
            ! $this->filled('table') => PrivilegeLevelEnum::Project,
            default => PrivilegeLevelEnum::Table,
        };
    }
}
