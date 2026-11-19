<?php

declare(strict_types=1);

namespace App\Services\SSH\Transports;

/**
 * Arguments joined into one command line, as Windows programs split it again (the rules of CommandLineToArgvW): a path
 * with spaces, like the key of `C:\Users\Jane Doe\.ssh\id_ed25519`, stays one argument.
 */
final class WindowsCommandLine
{
    /**
     * @param  list<string>  $arguments
     */
    public static function join(array $arguments): string
    {
        return implode(' ', array_map(self::quote(...), $arguments));
    }

    public static function quote(string $argument): string
    {
        if ($argument !== '' && preg_match('/[\s"]/', $argument) !== 1) {
            return $argument;
        }

        // The backslashes are only special before a quote: they are doubled there, and before the closing quote.
        return $argument
            |> (fn (string $value): string => preg_replace('/(\\\\*)"/', '$1$1\\\\"', $value))
            |> (fn (string $value): string => preg_replace('/(\\\\+)$/', '$1$1', $value))
            |> (fn (string $value): string => '"'.$value.'"');
    }
}
