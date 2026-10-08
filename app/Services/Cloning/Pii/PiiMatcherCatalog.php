<?php

namespace App\Services\Cloning\Pii;

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
                    transformation: self::normalizeTransformation($matcher['transformation'] ?? []),
                );
            }
        }

        return new self($matchers);
    }

    /**
     * Ramène la stratégie d'un matcher à son nom nu. Le fichier écrit « "null" » avec ses guillemets pour que
     * clonio génère la chaîne "null" dans le .cloning.yaml, et non la valeur YAML null qu'il refuse ensuite.
     */
    private static function normalizeTransformation(array $transformation): array
    {
        $transformation['strategy'] = trim((string) ($transformation['strategy'] ?? 'null'), '"\'');

        return $transformation;
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
        if (preg_match('#^/(.*)/([a-zA-Z]*)$#s', $pattern, $m) === 1) {
            return @preg_match('/'.$m[1].'/'.$m[2], $columnName) === 1;
        }

        if (str_contains($pattern, '*')) {
            $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/i';

            return preg_match($regex, $columnName) === 1;
        }

        return strcasecmp($pattern, $columnName) === 0;
    }
}
