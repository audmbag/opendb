<?php

namespace App\Services;

use Closure;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlWriter
{
    /**
     * @param  Closure|null  $comment  commentaire à placer au-dessus d'une clé
     * @param  Closure|null  $inline  écrire ce nœud en style [{...}]
     */
    public function __construct(
        private int $indent = 2,
        private ?Closure $comment = null,
        private ?Closure $inline = null,
    ) {}

    public function dump(array $data): string
    {
        return $this->node($data, 0, []);
    }

    private function node(array $data, int $level, array $path): string
    {
        $pad = str_repeat(' ', $level * $this->indent);
        $out = '';

        if (array_is_list($data)) {
            foreach ($data as $i => $item) {
                $p = [...$path, $i];

                if ($this->isLeaf($p, $item)) {
                    $out .= $pad.'- '.$this->inlineValue($item)."\n";

                    continue;
                }

                $child = $this->node($item, $level + 1, $p);
                $out .= $pad.'- '.substr($child, strlen($pad) + $this->indent);
            }

            return $out;
        }

        foreach ($data as $key => $value) {
            $p = [...$path, $key];

            if ($text = $this->comment?->__invoke($p, $value)) {
                $out .= $pad.'# '.$text."\n";
            }

            $out .= $this->isLeaf($p, $value)
                ? $pad.$this->key($key).': '.$this->inlineValue($value)."\n"
                : $pad.$this->key($key).":\n".$this->node($value, $level + 1, $p);
        }

        return $out;
    }

    /**
     * Toute clé autre qu'un identifiant simple est mise entre guillemets, pour que « 123 », « true », « .inf »
     * ou « key: value » soient relus comme des chaînes (Yaml::dump() laisse par exemple « .inf » nu).
     */
    private function key(int|string $key): string
    {
        $key = (string) $key;

        return preg_match('/^[a-z_][a-z0-9_]*$/i', $key) === 1 && Yaml::dump($key) === $key
            ? $key
            : "'".str_replace("'", "''", $key)."'";
    }

    private function isLeaf(array $path, mixed $value): bool
    {
        return ! is_array($value)
            || $value === []
            || (bool) $this->inline?->__invoke($path, $value);
    }

    private function inlineValue(mixed $value): string
    {
        return Yaml::dump($value, 0, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }
}
