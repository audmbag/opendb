<?php

namespace App\Console\Help;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The help of a command laid out as Symfony does, in the language of the console: its description, how to call it,
 * then its own arguments and options followed by --help. The other options of every command, like --quiet or --env,
 * still work but are not shown.
 */
class CommandHelp
{
    public function __construct(private readonly OutputInterface $output) {}

    public function print(Command $command): void
    {
        $definition = $this->definition($command);
        $width = $this->labelWidth($definition);

        $this->section('console.help.description', ['  '.$command->getDescription()]);

        $this->section('console.help.usage', array_map(
            fn (string $usage): string => '  '.OutputFormatter::escape($usage),
            [$command->getName().' '.$definition->getSynopsis(true), ...$command->getAliases(), ...$command->getUsages()],
        ));

        if ($definition->getArguments() !== []) {
            $this->section('console.help.arguments', array_map(
                fn (InputArgument $argument): string => $this->row($argument->getName(), $argument->getDescription(), $this->defaultValue($argument->getDefault()), $width),
                array_values($definition->getArguments()),
            ));
        }

        $this->section('console.help.options', array_map(
            fn (InputOption $option): string => $this->row(
                $this->optionLabel($option),
                $option->getDescription(),
                ($option->acceptValue() ? $this->defaultValue($option->getDefault()) : '').($option->isArray() ? ' <comment>'.__('console.help.multiple').'</comment>' : ''),
                $width,
            ),
            array_values($definition->getOptions()),
        ));

        if ($command->getHelp() !== '') {
            $this->section('console.help.help', ['  '.str_replace("\n", "\n  ", $command->getProcessedHelp())]);
        }
    }

    /**
     * The arguments and options of the command itself, without those Symfony merges into every command, then --help.
     */
    private function definition(Command $command): InputDefinition
    {
        $native = $command->getNativeDefinition();

        return new InputDefinition([
            ...array_values($native->getArguments()),
            ...array_values($native->getOptions()),
            new InputOption('help', 'h', InputOption::VALUE_NONE, __('console.options.help')),
        ]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function section(string $title, array $lines): void
    {
        $this->output->writeln('<comment>'.__($title).'</comment>');
        $this->output->writeln($lines);
        $this->output->writeln('');
    }

    private function row(string $label, string $description, string $suffix, int $width): string
    {
        $indentedDescription = (string) preg_replace('/\s*[\r\n]\s*/', "\n".str_repeat(' ', $width + 4), $description);

        return '  <info>'.$label.'</info>'.str_repeat(' ', $width - Helper::width($label) + 2).$indentedDescription.$suffix;
    }

    /**
     * Like -r, --rename=RENAME, --env[=ENV] or --ansi|--no-ansi.
     */
    private function optionLabel(InputOption $option): string
    {
        $value = $option->acceptValue() ? '='.strtoupper($option->getName()) : '';
        $value = $option->isValueOptional() ? "[{$value}]" : $value;
        $name = $option->isNegatable() ? "--{$option->getName()}|--no-{$option->getName()}" : "--{$option->getName()}{$value}";

        return ($option->getShortcut() ? "-{$option->getShortcut()}, " : '    ').$name;
    }

    private function labelWidth(InputDefinition $definition): int
    {
        return max([
            ...array_map(fn (InputArgument $argument): int => Helper::width($argument->getName()), array_values($definition->getArguments())),
            ...array_map(fn (InputOption $option): int => Helper::width($this->optionLabel($option)), array_values($definition->getOptions())),
        ]);
    }

    private function defaultValue(mixed $default): string
    {
        if ($default === null || $default === [] || $default === false) {
            return '';
        }

        $value = str_replace('\\\\', '\\', (string) json_encode($default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ' <comment>'.OutputFormatter::escape(__('console.help.default', ['value' => $value])).'</comment>';
    }
}
