<?php

namespace App\Ai\Tools\Mail;

use App\Ai\Tools\Database\ExportDatabase;
use App\Models\User;
use App\Services\Mailer\Data\OutgoingMail;
use App\Services\Mailer\MailerManager;
use App\Services\Mailer\RecipientGuard;
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
        protected string $projectPath,
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
        return 'Prépare un mail envoyé avec la configuration SMTP de l\'utilisateur. '
            .'Un formulaire prérempli avec tes valeurs est présenté à l\'utilisateur : il peut modifier les destinataires, '
            .'l\'objet, le corps et retirer des pièces jointes, puis envoyer le mail ou annuler. '
            .'Seuls les destinataires autorisés (voir `ManageMailRecipients` avec l\'action `list`) peuvent recevoir un mail. '
            .'Les pièces jointes doivent être des fichiers du dossier `'.ExportDatabase::EXPORT_DIRECTORY.'` du projet, '
            .'par exemple une archive créée par `ExportDatabase`. '
            .'N\'utilise ce tool que si l\'utilisateur demande un envoi dans la conversation en cours. '
            .'Après l\'appel, rapporte ce qui a réellement été envoyé (tel que renvoyé par ce tool), ou que l\'utilisateur a annulé.';
    }

    public function handle(Request $request): Stringable|string
    {
        $values = Cache::pull(static::submissionCacheKey((string) $request->toolCallId()));

        if (! is_array($values)) {
            return "L'utilisateur n'a pas soumis le formulaire : le mail n'a pas été envoyé.";
        }

        $submission = OutgoingMail::fromArray($values);

        try {
            $result = $this->mailerManager->send($this->user, $submission, $this->projectPath, ExportDatabase::EXPORT_DIRECTORY);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        $proposal = OutgoingMail::fromArray($request->all());
        $attachments = $result['attachments'] === [] ? 'aucune' : implode(', ', $result['attachments']);
        $changes = $this->changes($proposal, $submission);

        return implode("\n", [
            'Mail envoyé. Voici ce qui a réellement été envoyé :',
            '- Destinataires : '.implode(', ', $result['to']),
            "- Objet : « {$submission->subject} »",
            "- Pièces jointes : {$attachments}",
            $changes === []
                ? 'L\'utilisateur a envoyé ta proposition sans la modifier.'
                : "L'utilisateur a modifié ta proposition dans le formulaire avant l'envoi :\n".implode("\n", $changes)."\n"
                    .'Rapporte uniquement le mail réellement envoyé ci-dessus : ne mentionne plus les valeurs de ta proposition.',
        ]);
    }

    /**
     * Ce que l'utilisateur a changé dans le formulaire par rapport au mail proposé par l'agent.
     *
     * @return list<string>
     */
    protected function changes(OutgoingMail $proposal, OutgoingMail $sent): array
    {
        $changes = [];

        if (array_diff($proposal->to, $sent->to) !== [] || array_diff($sent->to, $proposal->to) !== []) {
            $changes[] = '- destinataires : tu avais proposé '.(implode(', ', $proposal->to) ?: 'aucun').', le mail est parti à '.implode(', ', $sent->to);
        }

        if ($proposal->subject !== $sent->subject) {
            $changes[] = "- objet : tu avais proposé « {$proposal->subject} », l'objet envoyé est « {$sent->subject} »";
        }

        if (trim($proposal->body) !== trim($sent->body)) {
            $changes[] = '- message : l\'utilisateur a réécrit le corps du mail';
        }

        $removed = array_diff($proposal->attachments, $sent->attachments);

        if ($removed !== []) {
            $changes[] = '- pièces jointes non envoyées : '.implode(', ', $removed);
        }

        return $changes;
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
                ->description('Corps du mail en texte brut : un court message, les données partent en pièce jointe. '
                    .'Sépare les paragraphes par de vrais retours à la ligne, jamais par les séquences littérales `\n` ou `\n\n`.')
                ->required(),
            'attachments' => $schema->array()
                ->items($schema->string())
                ->description('Chemins des pièces jointes, relatifs au dossier du projet (ex. `'.ExportDatabase::EXPORT_DIRECTORY.'/export.zip`).')
                ->nullable(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Saisie utilisateur requise via formulaire.');
    }
}
