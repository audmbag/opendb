<?php

declare(strict_types=1);

namespace App\Services\Cloning\Engine\Cloning;

use App\Services\Cloning\Engine\Data\Cloning\ColumnCloningConfigData;
use Error;
use Exception;
use Faker\Factory;
use Faker\Generator;

class AnonymizationEngine
{
    private readonly Generator $faker;

    private readonly string $runSalt;

    /**
     * Motifs de regexify déjà analysés, false pour ceux laissés à Faker.
     *
     * @var array<string, RegexifyPattern|false>
     */
    private array $regexifyPatterns = [];

    public function __construct(string $locale = 'en_US', ?string $runSalt = null)
    {
        $this->faker = Factory::create($locale);
        $this->runSalt = $runSalt ?? bin2hex(random_bytes(32));
    }

    public function getRunSalt(): string
    {
        return $this->runSalt;
    }

    public function transform(mixed $value, ColumnCloningConfigData $config): mixed
    {
        return match ($config->strategy) {
            'null' => null,
            'keep' => $value,
            'static' => $config->staticValue,
            'fake' => $this->applyFake($config),
            'hash' => $this->applyHash(is_scalar($value) ? (string) $value : '', $config),
            'mask' => $this->applyMask(is_scalar($value) ? (string) $value : '', $config),
            'template' => $this->applyTemplate($config),
            default => $value,
        };
    }

    private function applyFake(ColumnCloningConfigData $config): mixed
    {
        $method = $config->fakerMethod;

        if ($method === null || $method === '') {
            return null;
        }

        if ($method === 'regexify' && is_string($config->fakerArguments[0] ?? null)) {
            $pattern = $this->regexifyPatterns[$config->fakerArguments[0]] ??= RegexifyPattern::compile($config->fakerArguments[0]) ?? false;

            if ($pattern !== false) {
                return $pattern->generate();
            }
        }

        $result = $this->faker->{$method}(...$config->fakerArguments);

        if (is_array($result)) {
            return implode(' ', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $result));
        }

        return $result;
    }

    private function applyHash(string $value, ColumnCloningConfigData $config): string
    {
        $salt = $config->hashSalt ?? $this->runSalt;

        return hash($config->hashAlgorithm ?? 'sha256', $salt.$value);
    }

    private function applyTemplate(ColumnCloningConfigData $config): string
    {
        $template = $config->template;

        if ($template === null || $template === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{([a-zA-Z][a-zA-Z0-9]*)\}/',
            function (array $matches): string {
                $method = $matches[1];

                try {

                    $result = $this->faker->{$method}();
                } catch (Exception|Error) {
                    return '';
                }

                if (is_array($result)) {
                    return implode(' ', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $result));
                }

                return is_scalar($result) ? (string) $result : '';
            },
            $template,
        );
    }

    private function applyMask(string $value, ColumnCloningConfigData $config): string
    {
        $visible = $config->visibleChars ?? 0;
        $maskChar = $config->maskChar ?? '*';
        $preserve = $config->preserveFormat ?? false;

        if ($preserve) {
            $result = '';
            $alphanumSeen = 0;

            foreach (str_split($value) as $char) {
                if (! ctype_alnum($char)) {
                    $result .= $char;
                } elseif ($alphanumSeen < $visible) {
                    $result .= $char;
                    $alphanumSeen++;
                } else {
                    $result .= $maskChar;
                }
            }

            return $result;
        }

        $len = mb_strlen($value);

        if ($len <= $visible) {
            return $value;
        }

        return mb_substr($value, 0, $visible).str_repeat($maskChar, $len - $visible);
    }
}
