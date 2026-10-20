<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\DriverConfigRequest;
use App\Models\DriverConfig;
use App\Models\Project;
use App\Services\Cloning\CloningManager;
use App\Services\Cloning\Config\CloningColumn;
use App\Services\Cloning\Faker\CloningFakerCatalog;
use App\Services\SystemInfoService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\InputBag;

class SaveWithCloningRequest extends FormRequest
{
    public const array STRATEGIES = [...CloningColumn::AVAILABLE_STRATEGIES, 'remapping'];

    public const array HASH_ALGORITHMS = ['sha256', 'sha512', 'md5', 'sha1'];

    /**
     * Transformations validées, par table puis par colonne.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $validatedTables = [];

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
        return [
            ...$this->driverConfigRules(),
            ...$this->anonymisationRules(),
            ...$this->optionsRules(),
            ...$this->tablesRules(),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            $this->validateTables(...),
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->targetsSourceDatabase()) {
                    $validator->errors()->add(
                        'cloningDriverConfig.database',
                        'La base de données cible ne peut pas être la même que la base de données source.',
                    );
                }
            },
        ];
    }

    /**
     * Normalise l'option de création de la base cible en booléen, puis rétablit l'anonymisation telle qu'envoyée :
     * les middlewares globaux retirent les espaces et remplacent les chaînes vides par null,
     * ce qui fausserait les valeurs statiques et les arguments Faker.
     */
    protected function prepareForValidation(): void
    {
        $driverConfig = $this->input('cloningDriverConfig');

        if (is_array($driverConfig)) {
            $driverConfig['create_database_if_not_exists'] = filter_var(
                $driverConfig['create_database_if_not_exists'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );

            $this->merge(['cloningDriverConfig' => $driverConfig]);
        }

        $raw = $this->isJson() ? json_decode($this->getContent(), true) : null;

        if (is_array($raw) && array_key_exists('anonymisation', $raw)) {
            $this->merge(['anonymisation' => $raw['anonymisation']]);
        }
    }

    /**
     * Connexion de la base de données cible, rattachée à la connexion source du projet.
     *
     * @return array<string, array<mixed>>
     */
    protected function driverConfigRules(): array
    {
        $driverConfig = $this->input('cloningDriverConfig');

        return [
            'cloningDriverConfig' => ['required', 'array'],
            ...DriverConfigRequest::getValidationRules(
                new InputBag(is_array($driverConfig) ? $driverConfig : []),
                'cloningDriverConfig.',
            ),
            'cloningDriverConfig.from_id' => [
                'required',
                Rule::exists(DriverConfig::class, 'id')->where('project_id', $this->project()->id),
            ],
        ];
    }

    /**
     * Les transformations des colonnes sont validées à part, par validateTables().
     *
     * @return array<string, array<mixed>>
     */
    protected function anonymisationRules(): array
    {
        return [
            'anonymisation' => ['required', 'array'],
            'anonymisation.cloningId' => ['bail', 'required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[A-Za-z0-9]{8}$/', $this->cloningSessionExists(...)],
            'anonymisation.tables' => ['present', 'array'],
        ];
    }

    /**
     * Valide la transformation de chaque colonne avec ses seules règles, sans jamais placer un nom de table ou
     * de colonne dans une clé de règle : Laravel y lit « * » comme un joker, sans échappement possible, et une
     * colonne « *star » validerait aussi « polestar » avec ses propres règles.
     * Les erreurs sont rattachées au chemin réel de la colonne ; seuls les champs de sa stratégie sont conservés.
     */
    protected function validateTables(Validator $validator): void
    {
        $tables = $this->input('anonymisation.tables');

        foreach (is_array($tables) ? $tables : [] as $table => $columns) {
            $tableKey = "anonymisation.tables.{$table}";

            if (! is_array($columns)) {
                $validator->errors()->add($tableKey, "Les colonnes de la table « {$table} » doivent former un tableau.");

                continue;
            }

            $this->validatedTables[$table] = [];

            foreach ($columns as $column => $transformation) {
                if (! is_array($transformation)) {
                    $validator->errors()->add("{$tableKey}.{$column}", "La transformation de la colonne « {$column} » doit être un tableau.");

                    continue;
                }

                $columnValidator = ValidatorFactory::make($transformation, $this->transformationRules($transformation['strategy'] ?? null));

                if ($columnValidator->fails()) {
                    foreach ($columnValidator->errors()->messages() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add("{$tableKey}.{$column}.{$field}", $message);
                        }
                    }

                    continue;
                }

                $this->validatedTables[$table][$column] = $columnValidator->validated();
            }
        }
    }

    /**
     * Remplace les tables brutes par les transformations validées par validateTables().
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        if (isset($validated['anonymisation'])) {
            $validated['anonymisation']['tables'] = $this->validatedTables;
        }

        return data_get($validated, $key, $default);
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function transformationRules(mixed $strategy): array
    {
        return [
            'strategy' => ['required', 'string', Rule::in(self::STRATEGIES)],
            ...match ($strategy) {
                'static' => [
                    'value' => ['present', $this->scalarOrNull(...)],
                ],
                'fake' => [
                    'fakerMethod' => ['required', 'string', Rule::in(CloningFakerCatalog::methods())],
                    'fakerArguments' => ['present', 'list'],
                ],
                'hash' => [
                    'algorithm' => ['required', 'string', Rule::in(self::HASH_ALGORITHMS)],
                ],
                'mask' => [
                    'visibleChars' => ['required', 'integer', 'min:0'],
                    'maskChar' => ['required', 'string', 'size:1'],
                    'preserveFormat' => ['required', 'boolean'],
                ],
                'remapping' => [
                    'remappingUse' => ['nullable', 'string'],
                    'foreignKeys' => ['present', 'list'],
                    'foreignKeys.*.table' => ['required', 'string'],
                    'foreignKeys.*.column' => ['required', 'string'],
                    'foreignKeys.*.selfReferential' => ['required', 'boolean'],
                ],
                default => [],
            },
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function optionsRules(): array
    {
        return [
            'options' => ['required', 'array'],
            'options.enforce_column_types' => ['required', 'boolean'],
            'options.drop_unknown_tables' => ['required', 'boolean'],
            'options.drop_extra_columns' => ['required', 'boolean'],
            'options.disable_fk_checks' => ['required', 'boolean'],
            'options.break_on_failure' => ['required', 'boolean'],
        ];
    }

    /**
     * Tables inaccessibles à l'agent et tables ignorées au clonage : des listes vides sont acceptées.
     *
     * @return array<string, array<mixed>>
     */
    protected function tablesRules(): array
    {
        return [
            'agentDisabled' => ['present', 'list'],
            'agentDisabled.*' => ['required', 'string', 'distinct'],
            'cloningDisabled' => ['present', 'list'],
            'cloningDisabled.*' => ['required', 'string', 'distinct'],
        ];
    }

    /**
     * Vérifie que le fichier généré à l'étape d'anonymisation existe encore pour cet identifiant de clonage.
     */
    protected function cloningSessionExists(string $attribute, mixed $value, Closure $fail): void
    {
        $file = app(SystemInfoService::class)->applicationPath('tmp', $value, CloningManager::SOURCE_CONNECTION_NAME.'.cloning.yaml');

        if (! is_file($file)) {
            $fail('La session de clonage est introuvable ou a expiré, relancez la configuration du clonage.');
        }
    }

    protected function scalarOrNull(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && ! is_scalar($value)) {
            $fail("The {$attribute} must be a scalar value.");
        }
    }

    /**
     * Indique si la cible désigne la même base de données que la connexion source du projet.
     */
    protected function targetsSourceDatabase(): bool
    {
        $source = $this->project()->driver_config()->first();
        $target = $this->input('cloningDriverConfig');

        return $source !== null
            && $source->database == ($target['database'] ?? null)
            && $source->host == ($target['host'] ?? null)
            && $source->port == ($target['port'] ?? null);
    }

    protected function project(): Project
    {
        return $this->route('project');
    }
}
