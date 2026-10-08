<?php

namespace App\Enums;

enum OperatingSystemEnum: string
{
    case LINUX = 'linux';
    case WINDOWS = 'windows';
    case MAC = 'mac';

    /**
     * Système sur lequel tourne le processus PHP courant.
     */
    public static function current(): self
    {
        return self::fromFamily(PHP_OS_FAMILY);
    }

    /**
     * @param  string  $family  Valeur de PHP_OS_FAMILY (Windows, Darwin, Linux, BSD, Solaris, Unknown)
     */
    public static function fromFamily(string $family): self
    {
        return match ($family) {
            'Windows' => self::WINDOWS,
            'Darwin' => self::MAC,
            default => self::LINUX,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LINUX => 'Linux',
            self::WINDOWS => 'Windows',
            self::MAC => 'macOS',
        };
    }
}
