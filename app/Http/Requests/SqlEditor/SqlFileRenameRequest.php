<?php

namespace App\Http\Requests\SqlEditor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new path of a file or a folder of the SQL editor. Its form is checked by the store of the files, which owns the rule.
 */
class SqlFileRenameRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:1024'],
        ];
    }
}
