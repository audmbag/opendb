<?php

namespace App\Services\Cloning\Strategy;

use App\Services\Cloning\Faker\CloningFakerCatalog;
use App\Services\Cloning\Schema\DbColumnInfo;

final class CloningStrategyResolver
{
    private const array ORDER = ['keep', 'fake', 'hash', 'mask', 'null', 'static', 'remapping'];

    private const array GENERIC = ['keep', 'fake', 'hash', 'mask', 'null', 'static'];

    /**
     * @return string[]
     */
    public static function options(?DbColumnInfo $column, string $currentStrategy): array
    {
        $allowed = $column === null
            ? self::withoutSchema($currentStrategy)
            : self::forColumn($column);

        $allowed[] = 'keep';
        $allowed[] = $currentStrategy;

        return array_values(array_filter(
            self::ORDER,
            static fn (string $strategy): bool => in_array($strategy, $allowed, true),
        ));
    }

    /**
     * Remplace une stratégie « null » par la solution de repli la plus proche quand la colonne est NOT NULL.
     * Toute autre transformation, ou une colonne absente du schéma, est renvoyée telle quelle.
     */
    public static function adapt(CloningTransformation $transformation, ?DbColumnInfo $column): CloningTransformation
    {
        if ($transformation->strategy !== 'null' || $column === null || $column->nullable) {
            return $transformation;
        }

        return self::nonNullableFallback($column);
    }

    /**
     * Repli d'une stratégie « null » sur une colonne NOT NULL, parmi les stratégies que la colonne autorise :
     * une valeur fixe neutre, sinon un hachage (colonne unique), sinon une valeur générée, sinon la valeur d'origine.
     */
    private static function nonNullableFallback(DbColumnInfo $column): CloningTransformation
    {
        $allowed = self::forColumn($column);
        $canFake = in_array('fake', $allowed, true);

        if ($canFake && $column->baseType() === 'uuid') {
            return new CloningTransformation(strategy: 'fake', fakerMethod: 'uuid');
        }

        $value = self::neutralValue($column);
        if ($value !== null && in_array('static', $allowed, true)) {
            return new CloningTransformation(strategy: 'static', value: $value);
        }

        $algorithm = self::hashAlgorithmFitting($column);
        if ($algorithm !== null && in_array('hash', $allowed, true)) {
            return new CloningTransformation(strategy: 'hash', algorithm: $algorithm);
        }

        $method = CloningFakerCatalog::catalogFor($column)[0] ?? null;
        if ($method !== null && $canFake) {
            return new CloningTransformation(
                strategy: 'fake',
                fakerMethod: $method['fakerMethod'],
                fakerArguments: $method['fakerArguments'],
            );
        }

        return new CloningTransformation(strategy: 'keep');
    }

    /**
     * Valeur fixe qui « efface » la donnée tout en restant valide pour le type de la colonne, ou null s'il n'y en a pas.
     */
    private static function neutralValue(DbColumnInfo $column): string|int|bool|null
    {
        $length = $column->maxLength();

        return match ($column->category()) {
            'string' => $length === null || $length >= strlen('REDACTED') ? 'REDACTED' : '',
            'numeric' => 0,
            'boolean' => false,
            'json' => '{}',
            default => null,
        };
    }

    /**
     * Algorithme de hachage dont l'empreinte hexadécimale tient dans la colonne, ou null si aucune ne tient.
     */
    private static function hashAlgorithmFitting(DbColumnInfo $column): ?string
    {
        $length = $column->maxLength();

        return match (true) {
            $column->category() !== 'string' => null,
            $length === null || $length >= 64 => 'sha256',
            $length >= 40 => 'sha1',
            $length >= 32 => 'md5',
            default => null,
        };
    }

    private static function withoutSchema(string $currentStrategy): array
    {
        return $currentStrategy === 'remapping' ? ['keep', 'remapping'] : self::GENERIC;
    }

    private static function forColumn(DbColumnInfo $column): array
    {
        if ($column->primaryKey) {
            return $column->isNumeric() && ! $column->compositePrimaryKey
                ? ['keep', 'remapping']
                : ['keep'];
        }

        if ($column->foreignKey) {
            return $column->nullable ? ['keep', 'null'] : ['keep'];
        }

        $allowed = match ($column->category()) {
            'string' => ['keep', 'fake', 'hash', 'mask', 'null', 'static'],
            'numeric', 'boolean', 'datetime' => ['keep', 'fake', 'null', 'static'],
            'json' => ['keep', 'null', 'static'],
            'binary' => ['keep', 'null'],
            default => ['keep', 'null', 'static'],
        };

        return array_values(array_filter(
            $allowed,
            static fn (string $strategy): bool => match ($strategy) {
                'null' => $column->nullable,
                'static' => ! $column->unique,
                default => true,
            },
        ));
    }
}
