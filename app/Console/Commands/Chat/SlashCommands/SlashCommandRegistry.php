<?php

namespace App\Console\Commands\Chat\SlashCommands;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use Illuminate\Contracts\Container\Container;

/**
 * The commands of the chat, found by the name typed after the slash.
 */
class SlashCommandRegistry
{
    public const string PREFIX = '/';

    /**
     * In the order the help lists them.
     *
     * @var list<class-string<SlashCommand>>
     */
    private const array COMMANDS = [
        NewCommand::class,
        ConversationsCommand::class,
        RenameCommand::class,
        ArchiveCommand::class,
        DeleteCommand::class,
        ModelCommand::class,
        ModeCommand::class,
        EffortCommand::class,
        RetryCommand::class,
        OpenCommand::class,
        CsvCommand::class,
        AttachCommand::class,
        MultiCommand::class,
        ClearCommand::class,
        HelpCommand::class,
        QuitCommand::class,
    ];

    /**
     * @var list<SlashCommand>|null made when a command is first needed: a command may itself need this registry
     */
    private ?array $commands = null;

    public function __construct(
        private readonly Container $container,
        private readonly Terminal $terminal,
    ) {}

    /**
     * @return list<SlashCommand>
     */
    public function all(): array
    {
        return $this->commands ??= array_map($this->container->make(...), self::COMMANDS);
    }

    /**
     * @return list<string> the commands starting like what is typed, with their slash
     */
    public function matching(string $typed): array
    {
        return collect($this->all())
            ->map(fn (SlashCommand $command): string => self::PREFIX.$command->name())
            ->filter(fn (string $name): bool => str_starts_with($name, strtolower($typed)))
            ->values()
            ->all();
    }

    /**
     * Only the name of the command is read without its case: what follows is given as it was typed.
     */
    public function run(ChatSession $session, string $input): void
    {
        [$name, $arguments] = [...explode(' ', substr($input, strlen(self::PREFIX)), 2), ''];
        $name = strtolower($name);

        $command = array_find($this->all(), fn (SlashCommand $command): bool => $command->name() === $name || in_array($name, $command->aliases(), true));

        if ($command === null) {
            $this->terminal->failure(__('chat_cli.errors.unknown_command', ['command' => self::PREFIX.$name]));

            return;
        }

        $command->run($session, trim($arguments));
    }
}
