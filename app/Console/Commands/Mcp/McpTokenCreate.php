<?php

namespace App\Console\Commands\Mcp;

use App\Console\Commands\Concerns\AuthenticatesUser;
use App\Console\Commands\Concerns\ChoosesProject;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Services\Mcp\Exception\McpAccessException;
use App\Services\Mcp\McpAccessManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

use function Laravel\Prompts\text;

#[Signature('mcp:token-create
    {label? : console.commands.mcp_token_create.label}
    {--project= : console.commands.mcp_token_create.project}
    {--name= : console.options.name}')]
#[Description('console.commands.mcp_token_create.description')]
class McpTokenCreate extends Command
{
    use AuthenticatesUser, ChoosesProject, TranslatesCommand;

    public function handle(McpAccessManager $access): int
    {
        $user = $this->authenticateUser();

        if ($user === null) {
            return self::FAILURE;
        }

        Auth::setUser($user);

        $project = $this->chooseProject($user, $this->option('project'));

        if ($project === null) {
            return self::FAILURE;
        }

        $label = $this->argument('label') ?? text(__('mcp.fields.name'), required: true);

        try {
            $issued = $access->issue($user, $project, $label);
        } catch (McpAccessException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(__('mcp.messages.created', ['project' => $project->name]));
        $this->warn(__('mcp.messages.shown_once'));
        $this->newLine();
        $this->line($issued->plainToken);
        $this->newLine();
        $this->line(__('mcp.messages.client_config'));
        $this->line(json_encode($access->clientConfig($project, $issued->plainToken), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
