<?php

namespace App\Http\Requests;

use App\Database\Enums\DriverLabEnum;
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
        $unless_sqlite = 'required_unless:driver_config.driver_id,'.DriverLabEnum::SQLITE->value;

        return [
            'driver_config.driver_id' => ['required', 'string', Rule::exists(Driver::class, 'id')],
            'driver_config.host' => [$unless_sqlite, 'string'],
            'driver_config.port' => [$unless_sqlite, 'numeric'],
            'driver_config.database' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($input) {
                if ($input->get('driver_config.driver_id') === DriverLabEnum::SQLITE->value && ! is_file($value) && ! is_writable($value)) {
                    return $fail("The {$attribute} must be a valid file path and writable.");
                }
            }],
            'driver_config.username' => [$unless_sqlite, 'string'],
            'driver_config.password' => [$unless_sqlite, 'string'],
            'driver_config.ssh' => ['nullable', 'array'],
            'driver_config.ssh.host' => ['required_with:driver_config.ssh', 'string'],
            'driver_config.ssh.port' => ['nullable', 'numeric'],
            'driver_config.ssh.user' => ['required_with:driver_config.ssh', 'string'],
            'driver_config.ssh.id_file' => ['required_with:driver_config.ssh', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_file($value)) {
                    return $fail("The {$attribute} must be a valid file path.");
                }
            }],
        ];
    }
}
