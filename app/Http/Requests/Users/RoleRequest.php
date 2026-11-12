<?php

namespace App\Http\Requests\Users;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A role created or changed by who holds CREATE USER, like CREATE ROLE in MySQL.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', Role::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role instanceof Role ? $role->id : null)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('attributes.role_name'),
            'description' => __('attributes.description'),
        ];
    }
}
