<?php

namespace App\Services\Mcp\Data;

use App\Models\McpToken;

/**
 * A token just issued, with its value in clear: the only time it can be shown, since only its hash is kept.
 */
final readonly class IssuedMcpToken
{
    public function __construct(
        public McpToken $token,
        public string $plainToken,
    ) {}
}
