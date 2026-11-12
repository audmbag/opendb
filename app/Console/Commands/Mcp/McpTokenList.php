<?php

namespace App\Console\Commands\Mcp;

use App\Console\Commands\Concerns\AuthenticatesUser;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Models\McpToken;
use App\Services\Mcp\McpAccessManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

#[Signature('mcp:token-list {--name= : console.options.name}')]
#[Description('console.commands.mcp_token_list.description')]
class McpTokenList extends Command
{
    use AuthenticatesUser, TranslatesCommand;

    public function handle(McpAccessManager $access): int
    {
        $user = $this->authenticateUser();

        if ($user === null) {
            return self::FAILURE;
        }

        $tokens = $access->tokensOf($user);

        if ($tokens->isEmpty()) {
            $this->info(__('mcp.messages.no_tokens'));

            return self::SUCCESS;
        }

        table(
            [__('mcp.fields.id'), __('mcp.fields.name'), __('mcp.fields.project'), __('mcp.fields.created_at'), __('mcp.fields.last_used_at')],
            $tokens->map(fn (McpToken $token): array => [
                $token->id,
                $token->name,
                (string) $token->project?->name,
                $token->created_at?->toDateTimeString() ?? '',
                $token->last_used_at?->toDateTimeString() ?? __('mcp.messages.never_used'),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
