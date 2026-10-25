<?php

namespace App\Ai\Tools\Mail;

use App\Ai\Tools\Database\ExportDatabase;
use App\Models\User;
use App\Services\Mailer\Data\OutgoingMail;
use App\Services\Mailer\MailerManager;
use App\Services\Mailer\RecipientGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        protected string $projectPath,
    ) {}

    public function description(): Stringable|string
    {
        return 'Envoie un mail avec la configuration SMTP de l\'utilisateur, après son autorisation. '
            .'Seuls les destinataires autorisés (voir `ManageMailRecipients` avec l\'action `list`) peuvent recevoir un mail. '
            .'Les pièces jointes doivent être des fichiers du dossier `'.ExportDatabase::EXPORT_DIRECTORY.'` du projet, '
            .'par exemple une archive créée par `ExportDatabase`. '
            .'N\'utilise ce tool que si l\'utilisateur demande un envoi dans la conversation en cours.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $result = $this->mailerManager->send($this->user, $this->outgoingMail($request), $this->projectPath, ExportDatabase::EXPORT_DIRECTORY);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        $attachments = $result['attachments'] === [] ? '' : ' avec '.implode(', ', $result['attachments']);

        return 'Mail envoyé à '.implode(', ', $result['to']).$attachments.'.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->array()
                ->items($schema->string())
                ->description('Adresses des destinataires, toutes autorisées (au plus '.RecipientGuard::MAX_RECIPIENTS.').')
                ->required(),
            'subject' => $schema->string()
                ->description('Objet du mail.')
                ->required(),
            'body' => $schema->string()
                ->description('Corps du mail en texte brut : un court message, les données partent en pièce jointe.')
                ->required(),
            'attachments' => $schema->array()
                ->items($schema->string())
                ->description('Chemins des pièces jointes, relatifs au dossier du projet (ex. `'.ExportDatabase::EXPORT_DIRECTORY.'/export.zip`).')
                ->nullable(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $mail = $this->outgoingMail($request);
        $attachments = $mail->attachments === [] ? 'sans pièce jointe' : 'avec '.implode(', ', $mail->attachments);

        return Approval::required('Envoyer un mail à '.implode(', ', $mail->to)." — objet « {$mail->subject} » — {$attachments} ?");
    }

    protected function outgoingMail(Request $request): OutgoingMail
    {
        return new OutgoingMail(
            to: array_values(array_map(strval(...), (array) ($request['to'] ?? []))),
            subject: $request->string('subject')->trim()->toString(),
            body: $request->string('body')->toString(),
            attachments: array_values(array_map(strval(...), (array) ($request['attachments'] ?? []))),
        );
    }
}
