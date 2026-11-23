<?php

declare(strict_types=1);

namespace App\Support\ClassBundle;

use ReflectionClass;

/**
 * The classes, interfaces, traits and enums the running process declared from files, with what each needs to be
 * declared: BundleSegments orders a bundle from it.
 *
 * PHP lists a class where it compiled its file, which may come before the parent its declaration then loaded: the
 * order of the list is only a starting point. Besides its parents, interfaces and traits, declaring a class may load
 * the classes its methods return or take, to check them against those of its parent: track() records them as they
 * load.
 */
final class DeclaredSymbols
{
    /**
     * The classes the autoloader was asked for while each one was loading, by lowercase name: PHP takes a class by the
     * case its caller writes.
     *
     * @var array<string, list<string>>
     */
    private static array $loadedWhileLoading = [];

    /**
     * @var list<string>
     */
    private static array $loading = [];

    /**
     * Record from now on what each class loads while it loads, ahead of the autoloaders already registered.
     */
    public static function track(): void
    {
        $tracker = null;
        $tracker = static function (string $class) use (&$tracker): void {
            if (self::$loading !== []) {
                self::$loadedWhileLoading[strtolower(array_last(self::$loading))][] = $class;
            }

            self::$loading[] = $class;

            try {
                foreach (spl_autoload_functions() as $autoloader) {
                    if ($autoloader !== $tracker) {
                        $autoloader($class);
                    }

                    if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
                        return;
                    }
                }
            } finally {
                array_pop(self::$loading);
            }
        };

        spl_autoload_register($tracker, prepend: true);
    }

    /**
     * @return list<array{name: string, kind: 'class'|'interface'|'trait'|'enum', file: string, dependencies: list<string>, internalAncestors: array<string, string>}>
     */
    public static function describe(): array
    {
        return [
            ...self::describeAll(get_declared_interfaces(), 'interface'),
            ...self::describeAll(get_declared_traits(), 'trait'),
            ...self::describeAll(get_declared_classes(), 'class'),
        ];
    }

    /**
     * What loaded while the symbol did, by the names they were declared with.
     *
     * @return list<string>
     */
    private static function loadedWhileLoading(string $name): array
    {
        $loaded = array_filter(self::$loadedWhileLoading[strtolower($name)] ?? [], fn (string $class): bool => class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false));

        return array_values(array_map(fn (string $class): string => new ReflectionClass($class)->getName(), $loaded));
    }

    /**
     * @param  list<class-string>  $names
     * @param  'class'|'interface'|'trait'  $kind
     * @return list<array{name: string, kind: 'class'|'interface'|'trait'|'enum', file: string, dependencies: list<string>, internalAncestors: array<string, string>}>
     */
    private static function describeAll(array $names, string $kind): array
    {
        $symbols = [];

        foreach ($names as $name) {
            $reflection = new ReflectionClass($name);

            if ($name === self::class || $reflection->isInternal() || $reflection->isAnonymous() || $reflection->getFileName() === false) {
                continue;
            }

            $ancestors = [...array_values(class_parents($name) ?: []), ...array_values(class_implements($name) ?: [])];
            $internalAncestors = [];

            foreach ($ancestors as $ancestor) {
                $ancestorReflection = new ReflectionClass($ancestor);

                if ($ancestorReflection->isInternal()) {
                    $internalAncestors[$ancestor] = (string) $ancestorReflection->getExtensionName();
                }
            }

            $symbols[] = [
                'name' => $name,
                'kind' => $reflection->isEnum() ? 'enum' : $kind,
                'file' => $reflection->getFileName(),
                'dependencies' => array_values(array_unique([...$ancestors, ...$reflection->getTraitNames(), ...self::loadedWhileLoading($name)])),
                'internalAncestors' => $internalAncestors,
            ];
        }

        return $symbols;
    }
}
