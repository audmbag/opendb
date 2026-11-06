<?php

namespace App\Http\Requests\Concerns;

use App\Models\Provider;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The provider and the model chosen by the user: the application has no default one, so every request running an agent selects them.
 */
trait SelectsAiModel
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function aiModelRules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::exists(Provider::class, 'id')->whereNotNull('api_key')],
            'model' => [
                'bail',
                'required',
                'string',
                function (string $attribute, string $value, Closure $fail): void {
                    $providerId = $this->input('provider');
                    $provider = is_string($providerId) ? Provider::find($providerId) : null;

                    if (! $provider?->hasActiveModel($value)) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));
                    }
                },
            ],
        ];
    }

    public function selectedProvider(): Provider
    {
        return Provider::findOrFail($this->validated('provider'));
    }

    public function selectedModel(): string
    {
        return $this->validated('model');
    }
}
