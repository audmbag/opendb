<?php

namespace App\Http\Requests\Erd;

use App\Database\Enums\SchemaExportFormatEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The format the structure of the database is downloaded in.
 */
class ErdExportRequest extends FormRequest
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
            'format' => ['required', Rule::enum(SchemaExportFormatEnum::class)],
        ];
    }

    public function exportFormat(): SchemaExportFormatEnum
    {
        return SchemaExportFormatEnum::from($this->validated('format'));
    }
}
