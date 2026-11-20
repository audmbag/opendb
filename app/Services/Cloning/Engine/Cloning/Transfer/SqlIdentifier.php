<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning\Transfer;

use App\Services\Cloning\Engine\Enums\DatabaseConnectionType;

/**
 * Noms de tables et de colonnes échappés à la main : la grammaire de Laravel lirait « 1.5e3 » comme « 1 ».« 5e3 ».
 */
final class SqlIdentifier
{
    public static function quote(string $name, DatabaseConnectionType $type): string
    {
        return match ($type) {
            DatabaseConnectionType::Mysql, DatabaseConnectionType::MariaDB => '`'.str_replace('`', '``', $name).'`',
            default => '"'.str_replace('"', '""', $name).'"',
        };
    }

    /**
     * @param  list<string>  $names
     */
    public static function list(array $names, DatabaseConnectionType $type): string
    {
        return implode(', ', array_map(fn (string $name): string => self::quote($name, $type), $names));
    }
}
