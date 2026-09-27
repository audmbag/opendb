<?php

namespace App\Services\Cloning;

final readonly class PiiMatcherMatch
{
    public function __construct(
        public PiiMatcherDefinition $matcher,
        public string $matchedPattern,
    ) {}

    public function toArray(): array
    {
        return [
            //            'key' => $this->matcher->key,
            //            'group' => $this->matcher->group,
            'groupLabel' => $this->matcher->groupLabel,
            'name' => $this->matcher->name,
            'sensitivity' => $this->matcher->sensitivity,
            //            'matchedPattern' => $this->matchedPattern,
            //            'suggestedTransformation' => CloningTransformation::fromRaw($this->matcher->transformation)->toArray(),
        ];
    }
}
