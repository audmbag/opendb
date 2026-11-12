<?php

namespace App\Console\Commands\Mcp;

use App\Console\Commands\Concerns\TranslatesCommand;
use App\Services\Mcp\Data\McpSession;
use App\Services\Mcp\Exception\McpAccessException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Mcp\Server\Registrar;

/**
 * Started by an MCP client, never by hand: the token comes from the environment of the client, so that it stays out of
 * the arguments of the process. Standard output belongs to the protocol, so a refusal is only written on the error
 * output, before the server starts (mcp:start would let the exception reach the console).
 */
#[Signature('mcp:serve')]
#[Description('console.commands.mcp_serve.description')]
class McpServe extends Command
{
    use TranslatesCommand;

    public function handle(Registrar $registrar): int
    {
        try {
            $this->laravel->make(McpSession::class);
        } catch (McpAccessException $e) {
            $this->output->getErrorStyle()->writeln("<error>{$e->getMessage()}</error>");

            return self::FAILURE;
        }

        $registrar->getLocalServer('opendb')();

        return self::SUCCESS;
    }
}
