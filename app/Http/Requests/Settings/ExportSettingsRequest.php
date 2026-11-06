<?php

namespace App\Http\Requests\Settings;

use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExportFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The format and the compression of the exports, unless the user or the agent picks others.
 */
class ExportSettingsRequest extends FormRequest
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
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'compression' => ['required', Rule::enum(ExportCompression::class)],
        ];
    }
}
