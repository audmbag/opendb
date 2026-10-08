<?php

declare(strict_types=1);

namespace App\Services\Cloning\Faker;

use App\Services\Cloning\Pii\PiiMatcherMatch;
use App\Services\Cloning\Schema\DbColumnInfo;

/**
 * @phpstan-type FakerParameter array{name:string, type:string, default:mixed, nullable:bool, editable:bool, min:int|float|null, max:int|float|null, options:list<string>|null}
 * @phpstan-type FakerMethodOption array{fakerMethod:string, fakerArguments:list<mixed>, group:string, parameters:list<FakerParameter>}
 */
final class CloningFakerCatalog
{
    /**
     * Méthodes Faker acceptées par clonio (sa constante KNOWN_FAKER_METHODS) : toute autre méthode est refusée.
     */
    private const array KNOWN_METHODS = [
        'name', 'firstName', 'lastName', 'prefix', 'suffix', 'gender', 'title',
        'safeEmail', 'email', 'freeEmail', 'companyEmail', 'userName', 'phoneNumber',
        'e164PhoneNumber', 'tollFreePhoneNumber',
        'address', 'streetAddress', 'streetName', 'buildingNumber', 'city', 'state',
        'stateAbbr', 'postcode', 'country', 'countryCode', 'latitude', 'longitude',
        'company', 'companySuffix', 'jobTitle', 'iban', 'swiftBicNumber',
        'creditCardNumber', 'creditCardType', 'creditCardExpirationDate', 'currencyCode',
        'url', 'domainName', 'slug', 'ipv4', 'ipv6', 'macAddress', 'userAgent',
        'mimeType', 'fileExtension', 'md5', 'sha1', 'sha256', 'uuid',
        'word', 'words', 'sentence', 'sentences', 'paragraph', 'text',
        'randomNumber', 'numberBetween', 'randomFloat', 'numerify', 'lexify',
        'bothify', 'regexify', 'boolean', 'randomElement',
        'date', 'time', 'dateTime', 'dateTimeBetween', 'unixTime', 'year',
        'month', 'dayOfMonth', 'timezone',
        'ean13', 'ean8', 'isbn10', 'isbn13',
    ];

    private const array PARAMETERLESS = [
        'lastName' => 'person',
        'safeEmail' => 'contact',
        'userName' => 'contact',
        'phoneNumber' => 'contact',
        'url' => 'internet',
        'ipv4' => 'internet',
        'macAddress' => 'internet',
        'city' => 'address',
        'country' => 'address',
        'streetAddress' => 'address',
        'postcode' => 'address',
        'address' => 'address',
        'countryCode' => 'address',
        'state' => 'address',
        'buildingNumber' => 'address',
        'timezone' => 'address',
        'company' => 'work',
        'jobTitle' => 'work',
        'uuid' => 'code',
        'sha256' => 'code',
        'creditCardNumber' => 'payment',
        'iban' => 'payment',
    ];

    /**
     * @return list<string>
     */
    public static function methods(): array
    {
        return self::KNOWN_METHODS;
    }

    public static function supports(string $method): bool
    {
        return in_array($method, self::KNOWN_METHODS, true);
    }

    /**
     * @return list<FakerMethodOption>
     */
    public static function catalogFor(?DbColumnInfo $column): array
    {
        $entries = [];
        foreach (self::methodsFor($column) as $method) {
            if (($entry = self::definition($method, $column)) !== null) {
                $entries[$method] = $entry;
            }
        }

        return array_values($entries);
    }

    public static function catalogKey(array $catalog): ?string
    {
        return $catalog === [] ? null : substr(md5(json_encode($catalog)), 0, 12);
    }

    /**
     * Suggestion issue du matcher PII détecté, ou null s'il ne propose pas une méthode acceptée par clonio.
     *
     * @return FakerMethodOption|null
     */
    public static function suggestionFor(?DbColumnInfo $column, ?PiiMatcherMatch $detected): ?array
    {
        $suggestion = $detected?->matcher->transformation;
        if (! is_array($suggestion) || ($suggestion['strategy'] ?? null) !== 'fake' || ! isset($suggestion['faker_method'])) {
            return null;
        }

        $method = (string) $suggestion['faker_method'];
        if (! self::supports($method)) {
            return null;
        }

        $entry = self::definition($method, $column) ?? self::undescribed($method);
        $entry['fakerArguments'] = array_values($suggestion['faker_arguments'] ?? []);

        return $entry;
    }

    /**
     * @return list<string>
     */
    private static function methodsFor(?DbColumnInfo $column): array
    {
        if ($column === null) {
            return [];
        }

        return match ($column->category()) {
            'string' => self::stringMethods($column->maxLength()),
            'numeric' => $column->isInteger()
                ? ['numberBetween', 'randomNumber']
                : ['randomFloat', 'numberBetween'],
            'boolean' => ['boolean', 'numberBetween'],
            'datetime' => match ($column->baseType()) {
                'time', 'timetz' => ['time'],
                'year' => ['year'],
                default => ['date'],
            },
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private static function stringMethods(?int $length): array
    {
        $minLength = [
            'words' => 45,
            'sentence' => 100,
            'slug' => 100,
            'paragraph' => 500,
            'text' => 5,
            'name' => 45,
            'firstName' => 20,
            'lastName' => 15,
            'safeEmail' => 40,
            'userName' => 30,
            'phoneNumber' => 25,
            'city' => 45,
            'country' => 55,
            'streetAddress' => 50,
            'postcode' => 12,
            'company' => 45,
            'jobTitle' => 90,
            'uuid' => 36,
            'regexify' => 7,
            'bothify' => 7,
        ];

        $methods = [
            'words', 'sentence', 'text', 'paragraph',
            'name', 'firstName', 'lastName',
            'safeEmail', 'userName', 'phoneNumber',
            'city', 'country', 'streetAddress', 'postcode',
            'company', 'jobTitle',
            'uuid', 'slug', 'regexify', 'bothify',
        ];

        return array_values(array_filter(
            $methods,
            static fn (string $method): bool => $length === null || $length >= ($minLength[$method] ?? 0),
        ));
    }

    /**
     * @return FakerMethodOption|null
     */
    private static function definition(string $method, ?DbColumnInfo $column): ?array
    {
        if (! self::supports($method)) {
            return null;
        }

        $length = $column?->maxLength();
        [$low, $high] = $column === null ? [null, null] : self::numericBounds($column);
        // Colonne de type "date" (sans heure), ou inconnue : format court.
        $dateOnly = $column === null || $column->baseType() === 'date';

        return match ($method) {
            'words' => self::entry($method, 'text', [
                self::pInt('nb', 3, 1, 20),
                self::pBool('asText', true, editable: false), // false renverrait un tableau
            ]),
            'sentence' => self::entry($method, 'text', [
                self::pInt('nbWords', 6, 1, 50),
                self::pBool('variableNbWords', true),
            ]),
            'paragraph' => self::entry($method, 'text', [
                self::pInt('nbSentences', 3, 1, 20),
                self::pBool('variableNbSentences', true),
            ]),
            'text' => self::entry($method, 'text', [
                self::pInt('maxNbChars', min(200, $length ?? 200), 5, $length),
            ]),
            'slug' => self::entry($method, 'text', [
                self::pInt('nbWords', 6, 1, 20),
                self::pBool('variableNbWords', true),
            ]),
            'name', 'firstName' => self::entry($method, 'person', [
                self::pEnum('gender', null, ['male', 'female'], nullable: true),
            ]),
            'regexify' => self::entry($method, 'code', [self::pString('regex', '[A-Z]{3}[0-9]{4}')]),
            'bothify' => self::entry($method, 'code', [self::pString('pattern', '??-####')]),
            'numerify' => self::entry($method, 'code', [self::pString('pattern', '###-####')]),
            'numberBetween' => self::numberBetween($column, $low, $high),
            'randomNumber' => self::randomNumber($column),
            'randomFloat' => self::randomFloat($column, $low, $high),
            'boolean' => self::entry($method, 'boolean', [self::pInt('chanceOfGettingTrue', 50, 0, 100)]),
            'date' => self::entry($method, 'date', [
                self::pString('format', $dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s', editable: false),
                self::pDate('max', 'now'),
            ]),
            'time' => self::entry($method, 'date', [
                self::pString('format', 'H:i:s', editable: false),
                self::pDate('max', 'now'),
            ]),
            'year' => self::entry($method, 'date', [self::pDate('max', 'now')]),
            'latitude' => self::entry($method, 'address', [
                self::pFloat('min', -90, -90, 90),
                self::pFloat('max', 90, -90, 90),
            ]),
            'longitude' => self::entry($method, 'address', [
                self::pFloat('min', -180, -180, 180),
                self::pFloat('max', 180, -180, 180),
            ]),
            default => isset(self::PARAMETERLESS[$method])
                ? self::entry($method, self::PARAMETERLESS[$method], [])
                : null,
        };
    }

    /**
     * @return FakerMethodOption
     */
    private static function numberBetween(?DbColumnInfo $column, int|float|null $low, int|float|null $high): array
    {
        $low = $low === null ? null : (int) ceil($low);
        $high = $high === null ? null : (int) floor($high);

        $isBoolean = $column !== null && $column->category() === 'boolean';

        return self::entry('numberBetween', $isBoolean ? 'boolean' : 'number', [
            self::pInt('min', self::clamp(0, $low, $high), $low, $high),
            self::pInt('max', self::clamp($isBoolean ? 1 : 100, $low, $high), $low, $high),
        ]);
    }

    /**
     * @return FakerMethodOption
     */
    private static function randomNumber(?DbColumnInfo $column): array
    {
        $cap = 9;
        $range = $column?->integerRange();
        if ($range !== null) {
            $cap = min($cap, strlen((string) $range[1]) - 1);
        }
        $cap = max($cap, 1);

        return self::entry('randomNumber', 'number', [self::pInt('nbDigits', min(6, $cap), 1, $cap)]);
    }

    /**
     * @return FakerMethodOption
     */
    private static function randomFloat(?DbColumnInfo $column, int|float|null $low, int|float|null $high): array
    {
        $scale = $column?->decimalPrecision()[1] ?? null;

        return self::entry('randomFloat', 'number', [
            self::pInt('nbMaxDecimals', $scale ?? 2, 0, $scale),
            self::pFloat('min', self::clamp(0, $low, $high), $low, $high),
            self::pFloat('max', self::clamp(1000, $low, $high), $low, $high),
        ]);
    }

    /**
     * Bornes numériques d'une colonne : [min, max], chacune pouvant être null.
     *
     * @return array{0:int|float|null, 1:int|float|null}
     */
    private static function numericBounds(DbColumnInfo $column): array
    {
        if ($column->category() === 'boolean') {
            return [0, 1];
        }

        $range = $column->integerRange();
        if ($range !== null) {
            return $range;
        }

        $precision = $column->decimalPrecision();
        if ($precision !== null) {
            [$digits, $scale] = $precision;
            $max = min(round(10 ** ($digits - $scale) - 10 ** (-$scale), $scale), DbColumnInfo::SAFE_INTEGER);

            return [$column->isUnsigned() ? 0 : -$max, $max];
        }

        return [null, null];
    }

    private static function clamp(int|float $value, int|float|null $low, int|float|null $high): int|float
    {
        if ($low !== null && $value < $low) {
            $value = $low;
        }
        if ($high !== null && $value > $high) {
            $value = $high;
        }

        return $value;
    }

    /**
     * @param  list<FakerParameter>  $parameters
     * @return FakerMethodOption
     */
    private static function entry(string $method, string $group, array $parameters): array
    {
        $arguments = array_map(static fn (array $parameter): mixed => $parameter['default'], $parameters);

        while ($arguments !== [] && end($arguments) === null) {
            array_pop($arguments);
        }

        return [
            'fakerMethod' => $method,
            'fakerArguments' => array_values($arguments),
            'group' => $group,
            'parameters' => $parameters,
        ];
    }

    /**
     * Méthode acceptée par clonio mais dont les paramètres ne sont pas décrits ici.
     *
     * @return FakerMethodOption
     */
    private static function undescribed(string $method): array
    {
        return [
            'fakerMethod' => $method,
            'fakerArguments' => [],
            'group' => 'other',
            'parameters' => [],
        ];
    }

    /** @return FakerParameter */
    private static function parameter(string $name, string $type, mixed $default, bool $nullable, bool $editable, int|float|null $min, int|float|null $max, ?array $options): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'default' => $default,
            'nullable' => $nullable,
            'editable' => $editable,
            'min' => $min,
            'max' => $max,
            'options' => $options,
        ];
    }

    private static function pInt(string $name, int|float $default, int|float|null $min = null, int|float|null $max = null): array
    {
        return self::parameter($name, 'integer', (int) $default, false, true, $min, $max, null);
    }

    private static function pFloat(string $name, int|float $default, int|float|null $min = null, int|float|null $max = null): array
    {
        return self::parameter($name, 'float', (float) $default, false, true, $min, $max, null);
    }

    private static function pBool(string $name, bool $default, bool $editable = true): array
    {
        return self::parameter($name, 'boolean', $default, false, $editable, null, null, null);
    }

    private static function pString(string $name, string $default, bool $editable = true): array
    {
        return self::parameter($name, 'string', $default, false, $editable, null, null, null);
    }

    private static function pDate(string $name, string $default): array
    {
        return self::parameter($name, 'date-expression', $default, false, true, null, null, null);
    }

    /** @param list<string> $options */
    private static function pEnum(string $name, ?string $default, array $options, bool $nullable = false): array
    {
        return self::parameter($name, 'enum', $default, $nullable, true, null, null, $options);
    }
}
