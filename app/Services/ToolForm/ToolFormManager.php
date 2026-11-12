<?php

namespace App\Services\ToolForm;

use App\Ai\Tools\Database\CreateRecord;
use App\Ai\Tools\Mail\SendMail;
use App\Database\Exceptions\HiddenTableException;
use App\Database\Exceptions\PrivilegeException;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\TableRecordForm;
use App\Enums\PrivilegeEnum;
use App\Http\Requests\MailFormRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\Mailer\Data\OutgoingMail;
use App\Services\Mailer\Exception\InvalidMailException;
use App\Services\Mailer\MailAttachmentResolver;
use App\Services\Mailer\MailerManager;
use App\Services\Workspace\ProjectWorkspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Gateway between the application and the forms the user fills before approving a tool call: the record of a
 * CreateRecord call and the mail of a SendMail call. What the user submits is kept until the call is approved, the
 * same way for the web chat and for the console.
 */
class ToolFormManager
{
    private const int SUBMISSION_HOURS = 1;

    public function __construct(protected MailerManager $mailerManager) {}

    /**
     * The form adding a row to the table, generated from its columns.
     *
     * @throws HiddenTableException|PrivilegeException|TableNotFoundException
     */
    public function recordForm(Project $project, User $user, string $table): TableRecordForm
    {
        $driver = $project->driver_config->agentDriverFor($user);
        $driver->assertPrivilege(PrivilegeEnum::Insert, $table);

        return new TableRecordForm($driver, $table);
    }

    /**
     * Validate the values submitted for a CreateRecord call, and keep them until the user approves the call.
     *
     * @param  array<string, mixed>  $input
     * @return array{table: string, values: array<string, mixed>}
     *
     * @throws ValidationException|HiddenTableException|PrivilegeException|TableNotFoundException
     */
    public function submitRecord(Project $project, User $user, string $toolCallId, string $table, array $input): array
    {
        $form = $this->recordForm($project, $user, $table);

        $values = $form->normalize(Validator::make(
            $input,
            $form->rules(),
            attributes: collect($form->fields())->mapWithKeys(fn (array $field) => [$field['name'] => $field['name']])->all(),
        )->validate());

        $submission = ['table' => $form->table, 'values' => $values];

        Cache::put(CreateRecord::submissionCacheKey($toolCallId), $submission, now()->addHours(self::SUBMISSION_HOURS));

        return $submission;
    }

    /**
     * Describe the attachments the agent proposed for a SendMail call, with the size all of them may reach together.
     *
     * @param  array<string, mixed>  $arguments  the arguments of the tool call
     * @return array{files: list<array{path: string, name: string, extension: string, size: int|null, modified_at: string|null, error: string|null}>, maxTotalBytes: int}
     */
    public function mailAttachments(ProjectWorkspace $workspace, array $arguments): array
    {
        return [
            'files' => $this->mailerManager->describeAttachments($workspace->projectPath(), $workspace->relativePath(ProjectWorkspace::EXPORTS), OutgoingMail::fromArray($arguments)->attachments),
            'maxTotalBytes' => MailAttachmentResolver::MAX_TOTAL_BYTES,
        ];
    }

    /**
     * Validate the mail the user edited for a SendMail call, and keep it until the user approves the call.
     *
     * @param  array<string, mixed>  $input
     * @param  bool  $validated  true when the shape of the input is already checked, as a form request does
     *
     * @throws ValidationException
     */
    public function submitMail(User $user, Project $project, string $toolCallId, array $input, bool $validated = false): OutgoingMail
    {
        if (! $validated) {
            $request = new MailFormRequest;
            $input = Validator::make($input, $request->rules(), attributes: $request->attributes())->validate();
        }

        try {
            $workspace = $project->workspaceOf($user);
            $mail = $this->mailerManager->validate($user, OutgoingMail::fromArray($input), $workspace->projectPath(), $workspace->relativePath(ProjectWorkspace::EXPORTS));
        } catch (InvalidMailException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        Cache::put(SendMail::submissionCacheKey($toolCallId), $mail->toArray(), now()->addHours(self::SUBMISSION_HOURS));

        return $mail;
    }
}
