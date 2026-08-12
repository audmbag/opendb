<?php

namespace App\Http\Requests;

use App\Models\Driver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\InputBag;

class DriverConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::getValidationRules($this->request);
    }

    public static function getValidationRules(InputBag $input): array
    {
        return [
            'driver_config.driver_id' => ['required', 'string', Rule::exists(Driver::class, 'id')],
            'driver_config.host' => ['required_unless:driver_config.driver_id,sqlite', 'string'],
            'driver_config.port' => ['required_unless:driver_config.driver_id,sqlite', 'numeric'],
            'driver_config.database' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($input) {
                if ($input->get('driver_config.driver_id') === 'sqlite' && ! is_file($value) && ! is_writable($value)) {
                    return $fail("The {$attribute} must be a valid file path and writable.");
                }
            }],
            'driver_config.username' => ['required_unless:driver_config.driver_id,sqlite', 'string'],
            'driver_config.password' => ['required_unless:driver_config.driver_id,sqlite', 'string'],
        ];
    }
}
