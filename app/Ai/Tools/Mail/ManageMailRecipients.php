<?php

namespace App\Ai\Tools\Mail;

use App\Ai\Enums\MailRecipientActionEnum;
use App\Models\MailRecipient;
use App\Models\User;
use App\Repository\MailRecipientRepository;
use App\Support\TranslatableMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ManageMailRecipients implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        public User $user,
        protected MailRecipientRepository $recipients,
    ) {}

    public function description(): Stringable|string
    {
        return 'Manages the list of the recipients allowed to receive a mail through `SendMail`. '
            .'The `action` parameter is `list` (list the recipients), `add` (add `email`, with an optional `name`) '
            .'or `remove` (remove `email`). Adding and removing ask for the permission of the user, '
            .'unless the address is already allowed (add) or missing from the list (remove): the tool then answers you directly, '
            .'simply pass its answer on to the user.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $request->enum('action', MailRecipientActionEnum::class);
        $email = $request->string('email')->trim()->toString();

        if ($action === null) {
            return 'Unknown action: use `list`, `add` or `remove`.';
        }

        if ($action->requiresEmail() && $email === '') {
            return "The `email` parameter is required for the `{$action->value}` action.";
        }

        try {
            return match ($action) {
                MailRecipientActionEnum::List => $this->list(),
                MailRecipientActionEnum::Add => $this->add($email, $request->string('name')->trim()->toString()),
                MailRecipientActionEnum::Remove => $this->remove($email),
            };
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(array_column(MailRecipientActionEnum::cases(), 'value'))
                ->required()
                ->description('The operation to run: list, add or remove.'),
            'email' => $schema->string()
                ->description('Address of the recipient (required for add and remove).'),
            'name' => $schema->string()
                ->description('Name of the recipient (optional, for add).'),
        ];
    }

    /**
     * Only an addition or a removal that really changes the list asks for permission: otherwise the tool answers directly.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $email = mb_strtolower($request->string('email')->trim()->toString());

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return match ($request->enum('action', MailRecipientActionEnum::class)) {
            MailRecipientActionEnum::Add => $this->recipients->isAllowed($this->user, $email)
                ? false
                : Approval::required((new TranslatableMessage('ai.approvals.add_recipient', ['email' => $email]))->toJson()),
            MailRecipientActionEnum::Remove => $this->recipients->isListed($this->user, $email)
                ? Approval::required((new TranslatableMessage('ai.approvals.remove_recipient', ['email' => $email]))->toJson())
                : false,
            default => false,
        };
    }

    protected function list(): string
    {
        $recipients = $this->recipients->list($this->user);
        $sender = $this->user->mailSetting?->from_address;

        $lines = $recipients->map(fn (MailRecipient $recipient): string => '- '.$recipient->email.($recipient->name ? " ({$recipient->name})" : ''));

        if ($sender !== null) {
            $lines->prepend("- {$sender} (sender address of the user, always allowed)");
        }

        return $lines->isEmpty() ? 'No allowed recipient.' : "Allowed recipients:\n".$lines->implode("\n");
    }

    protected function add(string $email, string $name): string
    {
        $recipient = $this->recipients->add($this->user, $email, $name);

        return "\"{$recipient->email}\" is now one of the allowed recipients.";
    }

    protected function remove(string $email): string
    {
        $this->recipients->remove($this->user, $email);

        return "\"{$email}\" is no longer one of the allowed recipients.";
    }
}
