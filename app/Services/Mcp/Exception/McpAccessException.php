<?php

namespace App\Services\Mcp\Exception;

use RuntimeException;

/**
 * The access to the MCP server refused: a token missing or unknown, or a project the user may no longer open.
 */
class McpAccessException extends RuntimeException
{
    public static function missingToken(): self
    {
        return new self(__('mcp.errors.missing_token'));
    }

    public static function unknownToken(): self
    {
        return new self(__('mcp.errors.unknown_token'));
    }

    public static function projectOutOfReach(string $project): self
    {
        return new self(__('mcp.errors.project_out_of_reach', ['project' => $project]));
    }
}
