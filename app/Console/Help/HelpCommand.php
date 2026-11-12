<?php

namespace App\Console\Help;

use App\Console\ApplicationCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand as SymfonyHelpCommand;
use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The help of Symfony, which --help shows too, in the language of the console for the commands of the application,
 * with their own arguments and options and --help only. The commands of Laravel keep the help of Symfony.
 */
class HelpCommand extends SymfonyHelpCommand
{
    private ?Command $describedCommand = null;

    protected function configure(): void
    {
        $this->ignoreValidationErrors();

        $this
            ->setName('help')
            ->setDefinition([
                new InputArgument('command_name', InputArgument::OPTIONAL, __('console.commands.help.command_name'), 'help', fn (): array => array_keys((new ApplicationDescription($this->getApplication()))->getCommands())),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, __('console.commands.help.format'), 'txt', static fn (): array => (new DescriptorHelper)->getFormats()),
                new InputOption('raw', null, InputOption::VALUE_NONE, __('console.commands.help.raw')),
            ])
            ->setDescription(__('console.commands.help.description'));
    }

    public function setCommand(Command $command): void
    {
        $this->describedCommand = $command;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->describedCommand ?? $this->getApplication()->find($input->getArgument('command_name'));
        $this->describedCommand = null;

        if (! ApplicationCommands::contains($command) || $input->getOption('format') !== 'txt' || $input->getOption('raw')) {
            parent::setCommand($command);

            return parent::execute($input, $output);
        }

        (new CommandHelp($output))->print($command);

        return self::SUCCESS;
    }
}
