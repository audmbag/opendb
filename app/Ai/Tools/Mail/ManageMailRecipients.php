<?php

namespace App\Ai\Tools\Mail;

use App\Ai\Enums\MailRecipientActionEnum;
use App\Models\MailRecipient;
use App\Models\User;
use App\Repository\MailRecipientRepository;
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
        return 'Gère la liste des destinataires autorisés à recevoir un mail via `SendMail`. '
            .'Le paramètre `action` vaut `list` (lister les destinataires), `add` (ajouter `email`, avec un `name` facultatif) '
            .'ou `remove` (retirer `email`). L\'ajout et le retrait demandent l\'autorisation de l\'utilisateur.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $request->enum('action', MailRecipientActionEnum::class);
        $email = $request->string('email')->trim()->toString();

        if ($action === null) {
            return 'Action inconnue : utilise `list`, `add` ou `remove`.';
        }

        if ($action->requiresEmail() && $email === '') {
            return "Le paramètre `email` est obligatoire pour l'action `{$action->value}`.";
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
                ->description("L'opération à effectuer : list, add ou remove."),
            'email' => $schema->string()
                ->description('Adresse du destinataire (requise pour add et remove).'),
            'name' => $schema->string()
                ->description('Nom du destinataire (facultatif, pour add).'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $email = $request->string('email')->trim()->toString();

        return match ($request->enum('action', MailRecipientActionEnum::class)) {
            MailRecipientActionEnum::Add => Approval::required("Autoriser l'envoi de mails à « {$email} » ?"),
            MailRecipientActionEnum::Remove => Approval::required("Retirer « {$email} » des destinataires autorisés ?"),
            default => false,
        };
    }

    protected function list(): string
    {
        $recipients = $this->recipients->list($this->user);
        $sender = $this->user->mailSetting?->from_address;

        $lines = $recipients->map(fn (MailRecipient $recipient): string => '- '.$recipient->email.($recipient->name ? " ({$recipient->name})" : ''));

        if ($sender !== null) {
            $lines->prepend("- {$sender} (adresse d'expéditeur de l'utilisateur, toujours autorisée)");
        }

        return $lines->isEmpty() ? 'Aucun destinataire autorisé.' : "Destinataires autorisés :\n".$lines->implode("\n");
    }

    protected function add(string $email, string $name): string
    {
        $recipient = $this->recipients->add($this->user, $email, $name);

        return "« {$recipient->email} » fait maintenant partie des destinataires autorisés.";
    }

    protected function remove(string $email): string
    {
        $this->recipients->remove($this->user, $email);

        return "« {$email} » ne fait plus partie des destinataires autorisés.";
    }
}
