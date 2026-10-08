<?php

namespace App\Services\Cloning\Pii;

use App\Services\Cloning\Schema\DbColumnInfo;
use App\Services\Cloning\Strategy\CloningStrategyResolver;
use App\Services\Cloning\Strategy\CloningTransformation;

final readonly class PiiMatcherMatch
{
    public function __construct(
        public PiiMatcherDefinition $matcher,
        public string $matchedPattern,
    ) {}

    /**
     * @param  DbColumnInfo|null  $column  Colonne détectée, pour adapter la suggestion à ce qu'elle accepte
     */
    public function toArray(?DbColumnInfo $column = null): array
    {
        $suggested = CloningStrategyResolver::adapt(CloningTransformation::fromRaw($this->matcher->transformation), $column);

        return [
            'key' => $this->matcher->key,
            'group' => $this->matcher->group,
            'groupLabel' => $this->matcher->groupLabel,
            'name' => $this->matcher->name,
            'sensitivity' => $this->matcher->sensitivity,
            'matchedPattern' => $this->matchedPattern,
            'suggestedTransformation' => $suggested->toArray(),
        ];
    }
}
