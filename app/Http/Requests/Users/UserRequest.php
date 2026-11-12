<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * An account created or changed by who holds CREATE USER: its name, its password, required for a new account, and its roles.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->account();

        return $account === null
            ? $this->user()->can('create', User::class)
            : $this->user()->can('update', $account);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('users', 'name')->ignore($this->account()?->id)],
            'password' => [$this->account() === null ? 'required' : 'nullable', 'string', 'confirmed', Password::min(8)],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('attributes.username'),
            'password' => __('attributes.password'),
            'roles' => __('attributes.roles'),
            'roles.*' => __('attributes.role'),
        ];
    }

    private function account(): ?User
    {
        $account = $this->route('user');

        return $account instanceof User ? $account : null;
    }
}
