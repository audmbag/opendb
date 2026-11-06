<?php

namespace App\Http\Requests\SqlEditor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The content of a tab of the SQL editor, with the version of the file the tab read.
 */
class SqlFileWriteRequest extends FormRequest
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
            // An emptied tab is sent as an empty string, which the request turns into null.
            'sql' => ['present', 'nullable', 'string', 'max:1000000'],
            'version' => ['nullable', 'string', 'max:64'],
        ];
    }
}
