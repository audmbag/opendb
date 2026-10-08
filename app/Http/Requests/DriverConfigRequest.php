<?php

namespace App\Http\Requests;

use App\Database\Enums\DriverLabEnum;
use App\Models\Driver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'create_database_if_not_exists' => $this->boolean('create_database_if_not_exists'),
        ]);
    }

    /**
     * Données de connexion à persister (sans les options qui ne concernent que la vérification).
     *
     * @return array<string, mixed>
     */
    public function driverData(): array
    {
        return Arr::except($this->validated(), ['create_database_if_not_exists']);
    }

    public static function getValidationRules(InputBag $input): array
    {
        $unless_sqlite = 'required_unless:driver_id,'.DriverLabEnum::SQLITE->value;
        $create_database = filter_var($input->get('create_database_if_not_exists'), FILTER_VALIDATE_BOOLEAN);

        return [
            'id' => ['nullable', 'string'],
            'from_id' => ['nullable', 'string'],
            'driver_id' => ['required', 'string', Rule::exists(Driver::class, 'id')],
            'host' => [$unless_sqlite, 'string'],
            'port' => [$unless_sqlite, 'numeric'],
            'create_database_if_not_exists' => ['nullable', 'boolean'],
            'database' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($input, $create_database) {
                if ($input->get('driver_id') !== DriverLabEnum::SQLITE->value || is_file($value)) {
                    return;
                }

                if ($create_database ? ! is_writable(dirname($value)) : ! is_writable($value)) {
                    return $fail("The {$attribute} must be a valid file path and writable.");
                }
            }],
            'username' => [$unless_sqlite, 'string'],
            'password' => [$unless_sqlite, 'string'],
            'ssh' => ['nullable', 'array'],
            'ssh.host' => ['required_with:ssh', 'string'],
            'ssh.port' => ['nullable', 'numeric'],
            'ssh.user' => ['required_with:ssh', 'string'],
            'ssh.id_file' => ['required_with:ssh', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_file($value)) {
                    return $fail("The {$attribute} must be a valid file path.");
                }
            }],
        ];
    }
}
