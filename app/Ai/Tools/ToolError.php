<?php

namespace App\Ai\Tools;

use Stringable;

/**
 * The answer of a tool call that failed. The agent of OpenDB reads it as any other text, so that it corrects its call,
 * while the MCP server reports it as an error (isError) to the external clients.
 */
final readonly class ToolError implements Stringable
{
    public function __construct(public string $message) {}

    public function __toString(): string
    {
        return $this->message;
    }
}
