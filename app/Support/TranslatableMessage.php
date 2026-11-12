<?php

namespace App\Support;

use JsonSerializable;
use Stringable;

/**
 * A message saved for the interface by its key in lang/{locale}, so that it follows the language chosen in the settings
 * whenever it is shown again, instead of keeping the one it was written in.
 *
 * A parameter is a text, a number formatted in the language of the reader (see number()), or another message.
 */
final readonly class TranslatableMessage implements JsonSerializable, Stringable
{
    /**
     * The ways a number parameter is written: an integer, an estimated count of rows (« ~1 234 »), a cost with two
     * decimals, a share between 0 and 1 as a percentage, and a signed change between 0 and 1 (« −72 % »).
     */
    public const array NUMBER_FORMATS = ['integer', 'rows', 'decimal', 'percent', 'change'];

    /**
     * @param  array<string, string|int|float|TranslatableMessage|array{number: int|float, format: string}>  $params
     */
    public function __construct(
        public string $key,
        public array $params = [],
    ) {}

    /**
     * A number parameter, written by the reader in its language.
     *
     * @return array{number: int|float, format: string}
     */
    public static function number(int|float $value, string $format = 'integer'): array
    {
        return ['number' => $value, 'format' => $format];
    }

    /**
     * A message read back from a saved payload: the array of a translatable message becomes one again, any other
     * value is kept as it was written, like the texts saved before the messages were translatable.
     */
    public static function from(mixed $value): string|self
    {
        if ($value instanceof self || is_string($value)) {
            return $value;
        }

        if (is_array($value) && is_string($value['key'] ?? null)) {
            return new self($value['key'], array_map(
                fn (mixed $param): mixed => is_array($param) && isset($param['key']) ? self::from($param) : $param,
                (array) ($value['params'] ?? []),
            ));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The text of a message, translatable or not, in the current language.
     */
    public static function text(mixed $value): string
    {
        return (string) self::from($value);
    }

    /**
     * The message as a JSON string, for the places that only keep a string, like the reason of an approval.
     */
    public function toJson(): string
    {
        return (string) json_encode($this, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{key: string, params: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'params' => $this->params];
    }

    /**
     * The message in the current language, for the agent and the logs.
     */
    public function __toString(): string
    {
        return __($this->key, array_map($this->paramText(...), $this->params));
    }

    private function paramText(mixed $param): string
    {
        return match (true) {
            $param instanceof self => (string) $param,
            is_array($param) && isset($param['number']) => self::formatNumber($param['number'], (string) ($param['format'] ?? 'integer')),
            default => self::text($param),
        };
    }

    /**
     * A number in the current language: a narrow no-break space and a decimal comma in French, a comma and a decimal
     * point in English.
     */
    public static function formatNumber(int|float $number, string $format = 'integer'): string
    {
        [$decimalPoint, $thousands, $percent] = app()->getLocale() === 'fr' ? [',', "\u{202F}", "\u{00A0}%"] : ['.', ',', '%'];

        return match ($format) {
            'rows' => '~'.number_format($number, 0, $decimalPoint, $thousands),
            'decimal' => number_format($number, 2, $decimalPoint, $thousands),
            'percent' => number_format($number * 100, 0, $decimalPoint, $thousands).$percent,
            'change' => ($number < 0 ? '−' : '+').number_format(abs($number) * 100, 0, $decimalPoint, $thousands).$percent,
            default => number_format($number, 0, $decimalPoint, $thousands),
        };
    }
}
