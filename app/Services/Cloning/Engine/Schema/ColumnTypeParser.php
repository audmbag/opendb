<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Schema;

class ColumnTypeParser
{
    public static function parse(string $declaredType): array
    {
        $result = ['length' => null, 'precision' => null, 'scale' => null];

        if (preg_match('/\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/', $declaredType, $m) !== 1) {
            return $result;
        }

        $first = (int) $m[1];

        if (isset($m[2])) {

            $result['precision'] = $first;
            $result['scale'] = (int) $m[2];

            return $result;
        }

        $base = strtolower(trim($declaredType));
        $base = preg_replace('/\s*\(.*$/', '', $base) ?? $base;

        if (in_array($base, ['decimal', 'numeric', 'dec', 'number'], true)) {
            $result['precision'] = $first;
            $result['scale'] = 0;

            return $result;
        }

        $result['length'] = $first;

        return $result;
    }
}
