<?php

namespace App\Mcp\Tools;

use App\Ai\Tools\ToolError;
use App\Mcp\Tools\Concerns\LogsMcpCalls;
use App\Services\Mcp\Data\McpSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool as AiTool;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * A tool of the agent of OpenDB served to external agents as it is: same parameters, same guards, same answers. A
 * failed call (ToolError) is reported as an error, so that the client knows the call did not succeed.
 */
#[IsReadOnly]
class LaravelAiTool extends Tool
{
    use LogsMcpCalls;

    /**
     * @param  ?string  $mcpDescription  replaces the description of the tool when it speaks of the interface of OpenDB.
     */
    public function __construct(
        private readonly McpSession $session,
        private readonly AiTool $tool,
        private readonly ?string $mcpDescription = null,
    ) {}

    public function name(): string
    {
        return Str::snake(class_basename($this->tool));
    }

    public function title(): string
    {
        return Str::headline(class_basename($this->tool));
    }

    public function description(): string
    {
        return $this->mcpDescription ?? (string) $this->tool->description();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->tool->schema($schema);
    }

    public function handle(Request $request): Response
    {
        return $this->logged($this->session, $request->all(), function () use ($request): Response {
            $answer = $this->tool->handle(new AiRequest($request->all()));

            return $answer instanceof ToolError ? Response::error((string) $answer) : Response::text((string) $answer);
        });
    }
}
