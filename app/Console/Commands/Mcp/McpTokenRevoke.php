<?php

namespace App\Console\Commands\Mcp;

use App\Console\Commands\Concerns\AuthenticatesUser;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Models\McpToken;
use App\Services\Mcp\McpAccessManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

#[Signature('mcp:token-revoke
    {token? : console.commands.mcp_token_revoke.token}
    {--force : console.commands.mcp_token_revoke.force}
    {--name= : console.options.name}')]
#[Description('console.commands.mcp_token_revoke.description')]
class McpTokenRevoke extends Command
{
    use AuthenticatesUser, TranslatesCommand;

    public function handle(McpAccessManager $access): int
    {
        $user = $this->authenticateUser();

        if ($user === null) {
            return self::FAILURE;
        }

        $id = $this->argument('token');

        if ($id === null) {
            $tokens = $access->tokensOf($user);

            if ($tokens->isEmpty()) {
                $this->info(__('mcp.messages.no_tokens'));

                return self::SUCCESS;
            }

            $id = select(
                __('mcp.fields.token'),
                $tokens->mapWithKeys(fn (McpToken $token): array => [$token->id => "{$token->name} ({$token->project?->name})"])->all(),
                scroll: 10,
            );
        }

        $token = $access->findToken($user, $id);

        if ($token === null) {
            $this->error(__('mcp.errors.token_not_found', ['token' => $id]));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm(__('mcp.questions.revoke', ['name' => $token->name]), default: false)) {
            return self::SUCCESS;
        }

        $access->revoke($token);

        $this->info(__('mcp.messages.revoked', ['name' => $token->name]));

        return self::SUCCESS;
    }
}
