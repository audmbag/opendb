<?php

namespace App\Http\Requests;

use App\Models\Provider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Ai\Enums\Lab;

/**
 * The credentials connecting one of the providers.
 */
class ProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A local Ollama answers without a key, but needs the URL it listens on.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Provider $provider */
        $provider = $this->route('provider');

        return [
            'api_key' => [Rule::requiredIf($provider->requiresApiKey()), 'nullable', 'string'],
            'api_url' => [Rule::requiredIf($provider->driver() === Lab::Ollama), 'nullable', 'url'],
        ];
    }
}
