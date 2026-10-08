<?php

namespace App\Services\Cloning\Pii;

final class PiiMatcherDefinition
{
    public function __construct(
        public string $key,
        public string $group,
        public string $groupLabel,
        public string $name,
        public string $sensitivity,
        public bool $enabled,
        /** @var string[] */
        public array $patterns,
        /** @var array structure brute, passée telle quelle à ClonioTransformation::fromRaw() */
        public array $transformation,
    ) {
        $this->groupLabel = __('cloning.groups.'.$this->group);
        $this->name = __('cloning.matchers.'.$this->key);
    }
}
