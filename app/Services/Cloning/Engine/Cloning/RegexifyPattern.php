<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

/**
 * Motif de `regexify` analysé une fois pour toutes : Faker le réanalyse à chaque valeur, par une dizaine de
 * remplacements d'expressions régulières, ce qui coûte de 60 à 150 µs par ligne.
 *
 * Seuls les motifs faits de littéraux, de classes `[…]`, de `\d` et `\w`, suivis ou non de `{n}`, `{n,m}` ou `?`,
 * sont compilés, avec les règles et le tirage (mt_rand) de Faker\Provider\Base::regexify() ; les autres restent à Faker.
 */
final readonly class RegexifyPattern
{
    /**
     * @param  list<array{chars: list<string>, min: int, max: int}>  $tokens
     */
    private function __construct(private array $tokens) {}

    /**
     * Null si le motif sort de ce que la classe sait tirer.
     */
    public static function compile(string $regex): ?self
    {
        // Comme Faker : les ancres et les délimiteurs sont ignorés.
        $regex = preg_replace('/\$?\/?$/', '', preg_replace('/^\/?\^?/', '', $regex) ?? '') ?? '';
        $tokens = [];
        $length = strlen($regex);
        $position = 0;

        while ($position < $length) {
            $char = $regex[$position];

            if ($char === '[') {
                $end = strpos($regex, ']', $position + 1);

                if ($end === false || $end === $position + 1) {
                    return null;
                }

                $chars = self::classChars(substr($regex, $position + 1, $end - $position - 1));
                $position = $end + 1;
            } elseif ($char === '\\' && $position + 1 < $length) {
                $chars = match ($escaped = $regex[$position + 1]) {
                    'd' => str_split('0123456789'),
                    'w' => str_split('abcdefghijklmnopqrstuvwxyz'),
                    default => ctype_alnum($escaped) ? null : [$escaped],
                };
                $position += 2;
            } elseif (str_contains('()|.*+?{}]\\', $char)) {
                return null;
            } else {
                $chars = [$char];
                $position++;
            }

            if ($chars === null) {
                return null;
            }

            [$min, $max, $position] = self::quantifier($regex, $position);

            if ($min === null) {
                return null;
            }

            // Un caractère fixe s'ajoute au texte fixe qui le précède : « @example.org » se recopie d'un bloc.
            $previous = array_key_last($tokens);

            if (count($chars) === 1 && $min === $max && $previous !== null && $tokens[$previous]['min'] === 1 && $tokens[$previous]['max'] === 1 && count($tokens[$previous]['chars']) === 1) {
                $tokens[$previous]['chars'][0] .= str_repeat($chars[0], $min);
            } else {
                $tokens[] = count($chars) === 1 && $min === $max ? ['chars' => [str_repeat($chars[0], $min)], 'min' => 1, 'max' => 1] : ['chars' => $chars, 'min' => $min, 'max' => $max];
            }
        }

        return new self($tokens);
    }

    public function generate(): string
    {
        $value = '';

        foreach ($this->tokens as ['chars' => $chars, 'min' => $min, 'max' => $max]) {
            $last = count($chars) - 1;

            if ($last === 0 && $min === 1 && $max === 1) {
                $value .= $chars[0];

                continue;
            }

            $count = $min === $max ? $min : mt_rand($min, $max);

            for ($i = 0; $i < $count; $i++) {
                $value .= $chars[mt_rand(0, $last)];
            }
        }

        return $value;
    }

    /**
     * Caractères d'une classe, plages développées et barres obliques inverses retirées, comme Faker.
     *
     * @return list<string>|null
     */
    private static function classChars(string $class): ?array
    {
        $expanded = preg_replace_callback('/(\w|\d)\-(\w|\d)/', fn (array $range): string => implode('', range($range[1], $range[2])), $class);
        $chars = str_split((string) preg_replace('/\\\(?!\\\)/', '', (string) $expanded));

        return $chars === [''] ? null : $chars;
    }

    /**
     * @return array{?int, int, int} Bornes de la répétition, null si elle n'est pas prise en charge, et la suite du motif
     */
    private static function quantifier(string $regex, int $position): array
    {
        if (($regex[$position] ?? '') === '?') {
            return [0, 1, $position + 1];
        }

        if (($regex[$position] ?? '') !== '{') {
            return [1, 1, $position];
        }

        if (preg_match('/\G\{(\d+)(?:,(\d+))?\}/', $regex, $matches, 0, $position) !== 1) {
            return [null, 0, $position];
        }

        $min = (int) $matches[1];
        $max = isset($matches[2]) ? (int) $matches[2] : $min;

        return $max < $min ? [null, 0, $position] : [$min, $max, $position + strlen($matches[0])];
    }
}
