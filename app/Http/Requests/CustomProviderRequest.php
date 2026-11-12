<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Ai\Enums\Lab;

/**
 * A provider speaking the OpenAI API, added by the user with the models they use on it.
 */
class CustomProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The id names the provider in config('ai.providers'): it cannot take the name of a laravel/ai driver.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:25', 'regex:/^[a-z0-9_-]+$/', Rule::notIn(array_column(Lab::cases(), 'value')), Rule::unique('providers', 'id')],
            'label' => ['required', 'string', 'max:25', Rule::unique('providers', 'label')],
            'api_url' => ['required', 'url', 'max:255'],
            'api_key' => ['required', 'string'],
            'models' => ['required', 'array', 'min:1'],
            'models.*.id' => ['required', 'string', 'max:255', 'distinct'],
            'models.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id' => __('attributes.provider_id'),
            'label' => __('attributes.provider_label'),
            'api_url' => __('attributes.api_url'),
            'api_key' => __('attributes.api_key'),
            'models' => __('attributes.models'),
            'models.*.id' => __('attributes.model_id'),
            'models.*.label' => __('attributes.model_label'),
        ];
    }

    /**
     * The form sends its models as the JSON list of its rows, each with a key and a value: the rows left empty
     * are dropped, and each other one becomes a model with its id and its label.
     */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('models');

        if (is_string($rows)) {
            $rows = json_decode($rows, true);
        }

        if (! is_array($rows)) {
            return;
        }

        $this->merge([
            'models' => collect($rows)
                ->filter(fn (mixed $row): bool => is_array($row) && filled($row['key'] ?? null))
                ->map(fn (array $row): array => ['id' => trim((string) $row['key']), 'label' => $row['value'] ?? null])
                ->values()
                ->all(),
        ]);
    }
}
