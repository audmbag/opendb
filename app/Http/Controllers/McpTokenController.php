<?php

namespace App\Http\Controllers;

use App\Http\Requests\McpTokenRequest;
use App\Models\McpToken;
use App\Models\Project;
use App\Services\Mcp\Exception\McpAccessException;
use App\Services\Mcp\McpAccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class McpTokenController extends Controller
{
    public function __construct(private readonly McpAccessManager $access) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->access->tokensOf($request->user()),
        ]);
    }

    /**
     * The token in clear and the entry of the MCP client are only sent here: only the hash of the token is kept.
     */
    public function store(McpTokenRequest $request): JsonResponse
    {
        $project = Project::query()->findOrFail($request->validated('project_id'));

        try {
            $issued = $this->access->issue($request->user(), $project, $request->validated('name'));
        } catch (McpAccessException $e) {
            throw ValidationException::withMessages(['project_id' => $e->getMessage()]);
        }

        return response()->json([
            'data' => $issued->token,
            'token' => $issued->plainToken,
            'config' => $this->access->clientConfig($project, $issued->plainToken),
        ], 201);
    }

    public function destroy(Request $request, McpToken $mcpToken): JsonResponse
    {
        abort_unless($mcpToken->user_id === $request->user()->id, 404);

        $this->access->revoke($mcpToken);

        return response()->json(['message' => __('mcp.messages.revoked', ['name' => $mcpToken->name])]);
    }
}
