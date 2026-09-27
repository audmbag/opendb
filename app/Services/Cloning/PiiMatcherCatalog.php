<?php

namespace App\Services\Cloning;

final readonly class PiiMatcherCatalog
{
    /** @var PiiMatcherDefinition[] */
    private array $matchers;

    private function __construct(array $matchers)
    {
        $this->matchers = $matchers;
    }

    public static function fromRaw(array $raw): self
    {
        $matchers = [];
        foreach ($raw['groups'] ?? [] as $groupKey => $group) {
            foreach ($group['matchers'] ?? [] as $matcherKey => $matcher) {
                $matchers[] = new PiiMatcherDefinition(
                    key: $matcherKey,
                    group: $groupKey,
                    groupLabel: $group['name'] ?? $groupKey,
                    name: $matcher['name'],
                    sensitivity: $matcher['sensitivity'],
                    enabled: $matcher['enabled'] ?? true,
                    patterns: array_map('strval', $matcher['patterns'] ?? []),
                    transformation: $matcher['transformation'] ?? ['strategy' => 'null'],
                );
            }
        }

        return new self($matchers);
    }


    public function match(string $columnName): ?PiiMatcherMatch
    {
        foreach ($this->matchers as $matcher) {
            if (! $matcher->enabled) {
                continue;
            }

            foreach ($matcher->patterns as $pattern) {
                if (self::patternMatches($pattern, $columnName)) {
                    return new PiiMatcherMatch($matcher, $pattern);
                }
            }
        }

        return null;
    }

    private static function patternMatches(string $pattern, string $columnName): bool
    {
        // Regex complète : "/.../flags"
        if (preg_match('#^/(.*)/([a-zA-Z]*)$#s', $pattern, $m) === 1) {
            return @preg_match('/'.$m[1].'/'.$m[2], $columnName) === 1;
        }

        // Glob simple, ex. "*_email"
        if (str_contains($pattern, '*')) {
            $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/i';

            return preg_match($regex, $columnName) === 1;
        }

        // Correspondance exacte, insensible à la casse (ex. "reply_to")
        return strcasecmp($pattern, $columnName) === 0;
    }
}
