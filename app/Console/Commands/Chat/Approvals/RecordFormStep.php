<?php

namespace App\Console\Commands\Chat\Approvals;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Services\ToolForm\ToolFormManager;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\PendingApproval;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * The form of a CreateRecord call: one question for each column of the table, with the values the agent already knows.
 * The values are checked and kept as the web chat does, and the record is created once the call is approved.
 */
class RecordFormStep
{
    private const string EMPTY = '';

    public function __construct(
        private readonly ToolFormManager $forms,
        private readonly Terminal $terminal,
    ) {}

    /**
     * @return bool whether the user submitted the form; false when they gave up or the table cannot be filled
     */
    public function fill(ChatSession $session, PendingApproval $approval): bool
    {
        $table = (string) ($approval->arguments['table'] ?? '');
        $values = (array) ($approval->arguments['known_values'] ?? []);

        try {
            $fields = $this->forms->recordForm($session->project, $session->user, $table)->fields();
        } catch (RuntimeException $exception) {
            $this->terminal->failure($exception->getMessage());

            return false;
        }

        $this->terminal->line($this->terminal->theme->bold(__('ai.approvals.new_record')).$this->terminal->theme->muted(' · '.$table));
        $this->terminal->line();

        while (true) {
            $values = collect($fields)->mapWithKeys(fn (array $field): array => [$field['name'] => $this->ask($field, $values[$field['name']] ?? null)])->all();

            try {
                $this->forms->submitRecord($session->project, $session->user, $approval->id, $table, $values);

                return true;
            } catch (ValidationException $exception) {
                $this->terminal->lines(array_map(fn (string $error): string => $this->terminal->theme->danger($error), $exception->validator->errors()->all()));
                $this->terminal->line();
            }

            if (! confirm(__('chat_cli.forms.fix'))) {
                return false;
            }
        }
    }

    /**
     * @param  array{name: string, type: string, db_type: string, required: bool, options: ?list<string>}  $field
     * @return mixed the value typed, or null when the field is left empty
     */
    private function ask(array $field, mixed $known): mixed
    {
        $hint = $field['db_type'].($field['required'] ? ' · '.__('chat_cli.forms.required') : '');
        $default = is_scalar($known) ? (string) $known : self::EMPTY;

        $options = match (true) {
            $field['options'] !== null => array_combine($field['options'], $field['options']),
            $field['type'] === 'boolean' => ['1' => __('chat_cli.forms.yes'), '0' => __('chat_cli.forms.no')],
            default => null,
        };

        if ($options === null) {
            $value = text($field['name'], default: $default, hint: $hint);

            return $value === self::EMPTY ? null : $value;
        }

        $options = $field['required'] ? $options : [self::EMPTY => __('chat_cli.forms.empty'), ...$options];
        $default = is_bool($known) ? (string) (int) $known : $default;
        $value = (string) select($field['name'], $options, default: array_key_exists($default, $options) ? $default : null, hint: $hint);

        return $value === self::EMPTY ? null : $value;
    }
}
