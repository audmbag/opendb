<?php

namespace App\Console\Commands\Chat\Approvals;

use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Services\Mailer\Data\OutgoingMail;
use App\Services\ToolForm\ToolFormManager;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\PendingApproval;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

/**
 * The form of a SendMail call: the mail the agent proposes, which the user edits before it leaves. The mail is checked
 * and kept as the web chat does, and it is sent once the call is approved.
 */
class MailFormStep
{
    public function __construct(
        private readonly ToolFormManager $forms,
        private readonly Terminal $terminal,
    ) {}

    /**
     * @return bool whether the user submitted the mail; false when they gave up
     */
    public function fill(ChatSession $session, PendingApproval $approval): bool
    {
        $mail = OutgoingMail::fromArray($approval->arguments)->toArray();
        $files = $this->forms->mailAttachments($session->project->workspaceOf($session->user), $approval->arguments)['files'];

        while (true) {
            $mail = [
                'to' => preg_split('/[\s,;]+/', text(__('attributes.recipients'), default: implode(', ', $mail['to']), hint: __('chat_cli.forms.recipients_hint')), flags: PREG_SPLIT_NO_EMPTY),
                'subject' => text(__('attributes.subject'), default: $mail['subject']),
                'body' => textarea(__('attributes.body'), default: $mail['body']),
                'attachments' => $this->chooseAttachments($files, $mail['attachments']),
            ];

            try {
                $this->forms->submitMail($session->user, $session->project, $approval->id, $mail);

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
     * @param  list<array{path: string, name: string, size: int|null, error: string|null}>  $files  the files the agent proposed
     * @param  list<string>  $selected
     * @return list<string>
     */
    private function chooseAttachments(array $files, array $selected): array
    {
        if ($files === []) {
            return [];
        }

        $options = collect($files)->mapWithKeys(fn (array $file): array => [
            $file['path'] => $file['name'].' · '.($file['error'] ?? Number::fileSize($file['size'] ?? 0)),
        ])->all();

        $sendable = array_column(array_filter($files, fn (array $file): bool => $file['error'] === null), 'path');

        return array_values(multiselect(__('attributes.attachments'), $options, default: array_values(array_intersect($selected, $sendable))));
    }
}
