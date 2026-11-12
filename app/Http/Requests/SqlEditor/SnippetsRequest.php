<?php

namespace App\Http\Requests\SqlEditor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The snippets of the editor of a project, sent whole: those the user wrote, and the names of those of opendb hidden.
 */
class SnippetsRequest extends FormRequest
{
    /**
     * A name typed in the editor to find the snippet: letters, digits, dashes and underscores, starting with a letter.
     */
    public const string LABEL = '/^[a-z][a-z0-9_-]{0,39}$/i';

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
            'snippets' => ['present', 'array', 'max:200'],
            'snippets.*.label' => ['required', 'string', 'regex:'.self::LABEL, 'distinct:ignore_case'],
            'snippets.*.detail' => ['nullable', 'string', 'max:120'],
            'snippets.*.template' => ['required', 'string', 'max:10000'],
            'hidden' => ['present', 'array', 'max:200'],
            'hidden.*' => ['string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'snippets.*.label.regex' => __('requests.snippets.label'),
            'snippets.*.label.distinct' => __('requests.snippets.distinct'),
            'snippets.*.template.required' => __('requests.snippets.empty'),
        ];
    }
}
