<?php

namespace App\Http\Requests\Database;

use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExportFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The tables to export with what to keep of each of them, the format of the files and how they are packed.
 */
class DatabaseExportRequest extends FormRequest
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
            'tables' => ['required', 'array', 'min:1'],
            'tables.*.name' => ['required', 'string'],
            'tables.*.schema' => ['boolean'],
            'tables.*.data' => ['boolean'],
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'compression' => ['required', Rule::enum(ExportCompression::class)],
        ];
    }

    /**
     * @return array<string, array{schema: bool, data: bool}>
     */
    public function tables(): array
    {
        return collect($this->validated('tables'))->mapWithKeys(fn (array $table): array => [$table['name'] => [
            'schema' => (bool) ($table['schema'] ?? true),
            'data' => (bool) ($table['data'] ?? true),
        ]])->all();
    }
}
