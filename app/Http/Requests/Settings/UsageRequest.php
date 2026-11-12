<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The period of the usage shown in the settings: a number of days ending today, or the whole history.
 */
class UsageRequest extends FormRequest
{
    public const array PERIODS = ['7', '30', '90', '365', 'all'];

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
            'period' => ['sometimes', Rule::in(self::PERIODS)],
        ];
    }

    /**
     * The days of the period, or null for the whole history. The last 30 days unless another period is asked.
     */
    public function days(): ?int
    {
        $period = (string) $this->validated('period', '30');

        return $period === 'all' ? null : (int) $period;
    }
}
