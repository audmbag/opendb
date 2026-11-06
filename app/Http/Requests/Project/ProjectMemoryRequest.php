<?php

namespace App\Http\Requests\Project;

use App\Services\ProjectMemory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Every entry of the memory of the agent, in the order the user left them.
 */
class ProjectMemoryRequest extends FormRequest
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
            'entries' => ['present', 'array', 'max:'.ProjectMemory::MAX_ENTRIES],
            'entries.*.note' => ['required', 'string', 'max:1000'],
            'entries.*.date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'entries' => 'mémoire',
            'entries.*.note' => 'entrée',
            'entries.*.date' => 'date',
        ];
    }

    /**
     * @return list<array{date: ?string, note: string}>
     */
    public function entries(): array
    {
        return array_map(
            fn (array $entry): array => ['date' => $entry['date'] ?? null, 'note' => $entry['note']],
            array_values($this->validated('entries')),
        );
    }
}
