<?php

declare(strict_types=1);

namespace App\Services\SSH;

use Illuminate\Contracts\Cache\Repository;

/**
 * The processes using each SSH master, shared through the cache. A master serves every project and every user going
 * through the same server, so it may only be closed once no other request, console chat or MCP server still uses it.
 */
final class TunnelUsage
{
    /**
     * A process that died without releasing its master is forgotten after it: the master is then closed again early.
     */
    private const int TTL_SECONDS = 86400;

    /**
     * The masters this process holds, by their target, as many times as it opened a forward through them.
     *
     * @var array<string, int>
     */
    private array $held = [];

    public function __construct(private readonly Repository $cache) {}

    public function hold(string $target): void
    {
        $this->cache->add($this->key($target), 0, self::TTL_SECONDS);
        $this->cache->increment($this->key($target));

        $this->held[$target] = ($this->held[$target] ?? 0) + 1;
    }

    public function release(string $target): void
    {
        if (($this->held[$target] ?? 0) === 0) {
            return;
        }

        $this->held[$target]--;
        $this->cache->decrement($this->key($target));
    }

    /**
     * Whether another process than this one still uses the master.
     */
    public function isUsedElsewhere(string $target): bool
    {
        return (int) $this->cache->get($this->key($target), 0) > ($this->held[$target] ?? 0);
    }

    private function key(string $target): string
    {
        return 'ssh:master:'.$target.':users';
    }
}
