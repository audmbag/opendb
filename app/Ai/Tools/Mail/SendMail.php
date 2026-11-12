<?php

namespace App\Ai\Tools\Mail;

use App\Models\User;
use App\Services\Mailer\Data\OutgoingMail;
use App\Services\Mailer\MailerManager;
use App\Services\Mailer\RecipientGuard;
use App\Services\Workspace\ProjectWorkspace;
use App\Support\TranslatableMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class SendMail implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        public User $user,
        protected MailerManager $mailerManager,
        protected ProjectWorkspace $workspace,
    ) {}

    /**
     * The cache key of the mail the user submitted in the form of a tool call.
     */
    public static function submissionCacheKey(string $toolCallId): string
    {
        return "mail-submission:{$toolCallId}";
    }

    public function description(): Stringable|string
    {
        return 'Prepares a mail sent with the SMTP configuration of the user. '
            .'The attachments must be files of the `'.$this->workspace->relativePath(ProjectWorkspace::EXPORTS).'` folder of the project, '
            .'for example an archive created by `ExportDatabase`. '
            .'Only use this tool when the user asks for a mail in the current conversation. '
            .'After the call, report what was really sent (as returned by this tool), or that the user cancelled.';
    }

    public function handle(Request $request): Stringable|string
    {
        $values = Cache::pull(static::submissionCacheKey((string) $request->toolCallId()));

        if (! is_array($values)) {
            return 'The user did not submit the form: the mail was not sent.';
        }

        $submission = OutgoingMail::fromArray($values);

        try {
            $result = $this->mailerManager->send($this->user, $submission, $this->workspace->projectPath(), $this->workspace->relativePath(ProjectWorkspace::EXPORTS));
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        $proposal = OutgoingMail::fromArray($request->all());
        $attachments = $result['attachments'] === [] ? 'none' : implode(', ', $result['attachments']);
        $changes = $this->changes($proposal, $submission);

        return implode("\n", [
            'Mail sent. Here is what was really sent:',
            '- Recipients: '.implode(', ', $result['to']),
            "- Subject: \"{$submission->subject}\"",
            "- Attachments: {$attachments}",
            $changes === []
                ? 'The user sent your proposal without changing it.'
                : "The user changed your proposal in the form before sending it:\n".implode("\n", $changes)."\n"
                    .'Only report the mail really sent above: do not mention the values of your proposal anymore.',
        ]);
    }

    /**
     * What the user changed in the form compared to the mail proposed by the agent.
     *
     * @return list<string>
     */
    protected function changes(OutgoingMail $proposal, OutgoingMail $sent): array
    {
        $changes = [];

        if (array_diff($proposal->to, $sent->to) !== [] || array_diff($sent->to, $proposal->to) !== []) {
            $changes[] = '- recipients: you had proposed '.(implode(', ', $proposal->to) ?: 'none').', the mail was sent to '.implode(', ', $sent->to);
        }

        if ($proposal->subject !== $sent->subject) {
            $changes[] = "- subject: you had proposed \"{$proposal->subject}\", the sent subject is \"{$sent->subject}\"";
        }

        if (trim($proposal->body) !== trim($sent->body)) {
            $changes[] = '- message: the user rewrote the body of the mail';
        }

        $removed = array_diff($proposal->attachments, $sent->attachments);

        if ($removed !== []) {
            $changes[] = '- attachments not sent: '.implode(', ', $removed);
        }

        return $changes;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->array()
                ->items($schema->string())
                ->description('Addresses of the recipients, all allowed (at most '.RecipientGuard::MAX_RECIPIENTS.').')
                ->required(),
            'subject' => $schema->string()
                ->description('Subject of the mail.')
                ->required(),
            'body' => $schema->string()
                ->description('Body of the mail in plain text: a short message, the data goes in an attachment. '
                    .'Separate the paragraphs with real line breaks, never with the literal sequences `\n` or `\n\n`.')
                ->required(),
            'attachments' => $schema->array()
                ->items($schema->string())
                ->description('Paths of the attachments, relative to the folder of the project (e.g. `'.$this->workspace->relativePath(ProjectWorkspace::EXPORTS.'/export.zip').'`).')
                ->nullable(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required((new TranslatableMessage('ai.approvals.form_input'))->toJson());
    }
}
