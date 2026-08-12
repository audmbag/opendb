<?php

namespace App\Console\Commands;

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Enums\AgentAccessModeEnum;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Help;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\NoReturn;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\Error as AiStreamError;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Laravel\Prompts\Prompt;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\stream;
use function Laravel\Prompts\text;
use function Termwind\render;
use function Termwind\terminal;

#[Signature('app:chat')]
#[Description('Agent that can chat with users and answer questions.')]
#[Help("This command starts a chat agent that can answer your questions about databases. You can ask anything related to databases, and the agent will do its best to help you. Type 'exit' to quit the chat.")]
class Chat extends Command
{
    const array COMMANDS = [
        '/help',
        '/clear',
        '/exit',
        '/quit',
        '/mode',
    ];

    public string $mode = 'ask'; // or 'agent'

    public string $sessionId;

    public ?StreamableAgentResponse $agentResponse = null;

    private ?array $approvalDecision = null;

    #[NoReturn]
    public function handle(): void
    {
        Prompt::cancelUsing(function () {
            $this->close();
        });

        $this->trap(SIGINT, function () {
            $this->close();
        });

        $this->header();

        $count = 0;

        $this->sessionId = Str::random(16);

        do {
            $count++;

            $this->line($this->color('__________________________________________ ').$this->color(strtoupper($this->mode), '227').$this->color(' ________________________________________________'));

            $this->newLine();
            // $input = text("\e[38;5;39mYou:\e[0m", placeholder: $this->getPlaceholder($count > 1), default: "Comment créer une table, soit super bref");
            $input = text("\e[38;5;39mYou:\e[0m", placeholder: $this->getPlaceholder($count > 1));

            $this->newLine();

            $this->detectCommands($input);

        } while ($count);
    }

    public function detectCommands(string $input): void
    {
        $input = strtolower(trim($input));

        if (str_starts_with($input, '/') && ! in_array($input, self::COMMANDS)) {
            $this->error("Unknown command: {$input}");

            return;
        }

        switch ($input) {
            case '/exit':
            case '/quit':
                $this->close();
                break;
            case '/help':
                $this->printHelp();
                break;
            case '/clear':
                terminal()->clear();
                break;
            case '/mode':
                $this->switchMode();
                break;
            default:
                $this->runChatAgent($input);
        }
    }

    public function switchMode(): void
    {
        $modes = ['ask', 'agent'];

        $mode = select('Select a mode:', $modes, default: $this->mode, hint: "Switch between 'ask' (Q&A) and 'agent' (tutor) modes.");

        $this->line($this->color("Switched to mode: {$mode}"));

        $this->mode = $mode;
    }

    public function runChatAgent(string $message): void
    {
        spin(function () use ($message) {
            $prompt = $this->approvalDecision ? Decisions::from(collect($this->approvalDecision)->map(function ($decision) {
                return match ($decision['action']) {
                    'approved' => Decision::approve(),
                    'rejected' => Decision::reject($decision['result'] ?? null),
                };
            })->all()) : $message;

            $agent = new SQLDatabaseAgent(AgentAccessModeEnum::ReadWrite, Project::first());

            if ($this->agentResponse && $this->agentResponse->conversationId) {
                $agent->continue($this->agentResponse->conversationId, as: User::first());
            } else {
                $agent->forUser(User::first());
            }
            $this->agentResponse = $agent->stream($prompt);

            $stream = stream();

            foreach ($this->agentResponse as $chunk) {
                $stream->append(match (true) {
                    $chunk instanceof ToolCall => $this->renderToolCall($chunk),
                    $chunk instanceof ToolResult => $this->renderToolResult($chunk),
                    $chunk instanceof ToolApprovalRequest => $this->renderToolCallApproval($chunk),
                    $chunk instanceof ReasoningDelta => $this->renderReasoning($chunk),
                    $chunk instanceof TextDelta => $chunk->delta,
                    $chunk instanceof AiStreamError => "\n❌ {$chunk->message}\n",
                    default => '',
                });
            }

            $stream->close();

            return '';

        }, '  Chat Agent is thinking...');
    }

    private function renderToolCallApproval(ToolApprovalRequest $event): string
    {
        return $event->pendingApprovals
            ->map(fn (PendingApproval $approval) => $this->renderPendingApproval($approval));
    }

    private function renderPendingApproval(PendingApproval $approval): void
    {
        $name = $approval->tool;
        $arguments = $approval->arguments;
        $payload = $arguments['query'] ?? $arguments['command']
            ?? json_encode($arguments, JSON_UNESCAPED_UNICODE);

        $lang = $name === 'execute_command' ? 'bash' : 'sql';

        $this->line("```{$lang}\n{$payload}\n```\n");

        $approvalChoice = select(
            label: "\n\n⚠️ Approbation requise pour `{$name}`\n",
            options: ['approved' => 'Approuver', 'rejected' => 'Réjeter'],
            default: 'approved',
            hint: "\e[2m{$approval->reason}\e[0m\n"
        );

        $this->approvalDecision[] = [
            'action' => $approvalChoice,
            'result' => '',
        ];
    }

    private function renderReasoning(ReasoningDelta $event): string
    {
        $delta = $this->reasoningDeltaText($event);

        if ($delta === '') {
            return '';
        }

        return "\e[2;3m{$delta}\e[0m";
    }

    private function reasoningDeltaText(object $event): string
    {
        foreach (['delta', 'text', 'content', 'reasoning', 'value'] as $property) {
            if (isset($event->{$property}) && $event->{$property} !== '') {
                return (string) $event->{$property};
            }
        }

        return '';
    }

    private function renderToolCall(ToolCall $event): string
    {
        $call = $event->toolCall;
        $name = $call->name;
        $arguments = $this->toolCallArguments($call);

        $payload = $arguments['query'] ?? $arguments['command'] ?? null;

        if ($payload !== null) {
            $lang = $name === 'execute_command' ? 'bash' : 'sql';

            return "\n\n🔧 **{$name}**\n```{$lang}\n{$payload}\n```\n";
        }

        return "\n\n🔧 **{$name}** ".json_encode($arguments, JSON_UNESCAPED_UNICODE)."\n";
    }

    private function renderToolResult(ToolResult $event): string
    {
        $output = $this->toolResultOutput($event->toolResult);

        return '✅ '.Str::limit((string) $output, 300)."\n\n";
    }

    private function toolCallArguments(object $call): array
    {
        foreach (['arguments', 'input', 'parameters', 'args'] as $property) {
            if (isset($call->{$property})) {
                return (array) $call->{$property};
            }
        }

        return [];
    }

    private function toolResultOutput(object $result): mixed
    {
        foreach (['result', 'output', 'content', 'value'] as $property) {
            if (isset($result->{$property})) {
                return $result->{$property};
            }
        }

        return $result;
    }

    public function printHelp(): void
    {
        $this->line("<comment>SQL Expert CLI</comment> version <info>1.0.0</info>\n");

        // 2. Section USAGE
        $this->line('<comment>Usage:</comment>');
        $this->line("  command [options] [arguments]\n");

        // 3. Section OPTIONS
        $this->line('<comment>Options:</comment>');
        $this->line(sprintf('  %-20s %s', '<info>-h, --help</info>', 'Display help for the given command.'));
        $this->line(sprintf('  %-20s %s', '<info>-q, --quiet</info>', 'Do not output any message.'));
        $this->line('');

        // 4. Section COMMANDS (Le cœur de ton app)
        $this->line('<comment>Available Commands:</comment>');

        // Sous-catégorie Session
        $this->line(' <comment>session</comment>');
        $this->line(sprintf('  %-20s %s', '<info>/help</info>', 'Display this help menu.'));
        $this->line(sprintf('  %-20s %s', '<info>/mode [type]</info>', 'Switch mode between <comment>ask</comment> (Q&A) and <comment>agent</comment> (tutor).'));
        $this->line(sprintf('  %-20s %s', '<info>/quit, /exit</info>', 'Safely exit the interactive CLI session.'));

        // Sous-catégorie History & Utils
        $this->line(' <comment>utils</comment>');
        $this->line(sprintf('  %-20s %s', '<info>/clear</info>', 'Clear the terminal screen.'));
        $this->line(sprintf('  %-20s %s', '<info>/history</info>', 'Show the list of queries executed during this session.'));
        $this->line('');
    }

    public function color(string $text, string $color = '39'): string
    {
        return "\e[38;5;{$color}m{$text}\e[0m";
    }

    public function header(): void
    {
        echo "\e[2J\e[H\e[3J";

        $this->newLine();

        $this->printASCII([
            '  ██████╗  ██████╗  ███████╗ ███╗   ██╗ ██████╗  ██████╗ ',
            ' ██╔═══██╗ ██╔══██╗ ██╔════╝ ████╗  ██║ ██╔══██╗ ██╔══██╗',
            ' ██║   ██║ ██████╔╝ █████╗   ██╔██╗ ██║ ██║  ██║ ██████╔╝',
            ' ██║   ██║ ██╔═══╝  ██╔══╝   ██║╚██╗██║ ██║  ██║ ██╔══██╗',
            ' ╚██████╔╝ ██║      ███████╗ ██║ ╚████║ ██████╔╝ ██████╔╝',
            '  ╚═════╝  ╚═╝      ╚══════╝ ╚═╝  ╚═══╝ ╚═════╝  ╚═════╝ ',
        ]);

        $this->line("  Opendb - \e[38;5;213mVersion 1.0.0\e[0m");
        $this->line("  Hi, I'm your Opendb. I can help you with your database related questions and tasks. Let's chat!");

        $this->newLine();

        render(<<<'HTML'
            <div class="ml-2">
                <span class="px-1 bg-green-600"> Documentation</span>
                <a class="ml-1" href="https://laravel.com/docs/10.x/artisan" target="_blank">
                    https://laravel.com/docs/10.x/artisan
                </a>
            </div>
        HTML);

        $this->newLine();

        render(<<<'HTML'
            <div class="ml-2">
                <span class="pl-1 pr-1 bg-red-500">!</span> Please use the web interface for better experience by running <span class="px-1 text-blue-600">opendb web</span>
            </div>
        HTML);

        $this->newLine();

        render(<<<'HTML'
            <div class="ml-2">
                <div>  Type <span class="text-blue-400">/exit</span> to quit.</div>
                <div>  Type <span class="text-blue-400">/help</span> for more information.</div>
            </div>
        HTML);

        $this->newLine();
    }

    #[NoReturn]
    public function close(): void
    {
        terminal()->clear();

        $this->printASCII([
            '  ██████╗  ██████╗  ███████╗ ███╗   ██╗ ██████╗  ██████╗ ',
            ' ██╔═══██╗ ██╔══██╗ ██╔════╝ ████╗  ██║ ██╔══██╗ ██╔══██╗',
            ' ██║   ██║ ██████╔╝ █████╗   ██╔██╗ ██║ ██║  ██║ ██████╔╝',
            ' ██║   ██║ ██╔═══╝  ██╔══╝   ██║╚██╗██║ ██║  ██║ ██╔══██╗',
            ' ╚██████╔╝ ██║      ███████╗ ██║ ╚████║ ██████╔╝ ██████╔╝',
            '  ╚═════╝  ╚═╝      ╚══════╝ ╚═╝  ╚═══╝ ╚═════╝  ╚═════╝ ',
        ]);

        $this->line("  Opendb - \e[38;5;213mVersion 1.0.0\e[0m");

        $this->newLine();

        $this->info('  Goodbye!');

        $this->info("  Continue by running : \e[38;5;39mopendb --session={$this->sessionId}\e[0m");

        $this->newLine();

        exit(0);
    }

    public function printASCII(array $lines): void
    {
        $gradient = [213, 177, 141, 105, 69, 39];

        foreach ($lines as $index => $line) {
            $color = $gradient[$index];
            $this->line("\e[38;5;{$color}m{$line}\e[0m");
        }
    }

    public function getPlaceholder(bool $isFirst): string
    {
        if ($isFirst) {
            return 'Ask me anything about your database...';
        }

        $prompts = [
            'What is the current status of my database?',
            'Can you help me optimize my database queries?',
            'How can I backup my database?',
            'What are the best practices for database security?',
            'Can you explain the concept of database normalization?',
        ];

        return $prompts[array_rand($prompts)];
    }
}
