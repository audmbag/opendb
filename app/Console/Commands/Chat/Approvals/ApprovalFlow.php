<?php

namespace App\Console\Commands\Chat\Approvals;

use App\Ai\Tools\Database\CreateRecord;
use App\Ai\Tools\Database\WriteQuery;
use App\Ai\Tools\Mail\SendMail;
use App\Console\Commands\Chat\ChatSession;
use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Output\Theme;
use App\Console\Commands\Chat\Tools\ToolRendererRegistry;
use App\Database\QueryBindings;
use App\Support\TranslatableMessage;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Throwable;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Asks the user what to do with the tool calls the agent may not run alone, one call at a time.
 */
class ApprovalFlow
{
    private const string APPROVE = 'approve';

    private const string REJECT = 'reject';

    /**
     * The tools approved by filling a form, each with the step asking it.
     *
     * @var array<class-string, class-string<RecordFormStep|MailFormStep>>
     */
    private const array FORMS = [
        CreateRecord::class => RecordFormStep::class,
        SendMail::class => MailFormStep::class,
    ];

    public function __construct(
        private readonly Terminal $terminal,
        private readonly ToolRendererRegistry $toolRenderers,
        private readonly Container $container,
    ) {}

    /**
     * The decisions of the user, each under the id of its tool call: the agent matches them by this id.
     *
     * @param  Collection<int, PendingApproval>  $approvals
     * @param  bool  $callsShown  whether the answer that stopped on these calls has just been written, with what they ask
     */
    public function decide(ChatSession $session, Collection $approvals, bool $callsShown): Decisions
    {
        return Decisions::from(
            $approvals->mapWithKeys(fn (PendingApproval $approval): array => [$approval->id => $this->decision($session, $approval, $callsShown)])->all()
        );
    }

    private function decision(ChatSession $session, PendingApproval $approval, bool $callShown): Decision
    {
        $this->describe($session, $approval, $callShown);

        if (! $session->interactive) {
            return $this->rejected(__('chat_cli.approvals.not_interactive'));
        }

        $form = array_find(self::FORMS, fn (string $step, string $tool): bool => $this->isOneOf($approval, [$tool]));

        $choice = select(__('ai.approvals.required'), [
            self::APPROVE => $form === null ? __('ai.approvals.approve') : __('chat_cli.forms.fill'),
            self::REJECT => __('ai.approvals.reject'),
        ], default: self::REJECT);

        if ($choice === self::REJECT) {
            return Decision::reject(text(__('chat_cli.approvals.reason'), hint: __('chat_cli.approvals.reason_hint')) ?: null);
        }

        // The call of a form is approved once the form is submitted: the tool then reads what the user typed.
        if ($form !== null && ! $this->container->make($form)->fill($session, $approval)) {
            return $this->rejected(__('chat_cli.forms.cancelled'));
        }

        return Decision::approve();
    }

    /**
     * What the agent wants to run, what it would change and why it needs an approval.
     */
    private function describe(ChatSession $session, PendingApproval $approval, bool $callShown): void
    {
        $theme = $this->terminal->theme;

        $this->terminal->openFrame($theme->warning(Theme::WARNING).' '.$theme->bold($approval->tool).$theme->muted(' · '.__('ai.activities.waiting_approval')));

        if (! $callShown) {
            $this->terminal->frameLines($this->toolRenderers->for($approval->tool)->call($approval->arguments));
        }

        if (($affectedRows = $this->affectedRows($session, $approval)) !== null) {
            $this->terminal->frameLines([$theme->warning($affectedRows)]);
        }

        if (filled($approval->reason)) {
            $this->terminal->frameLines([$theme->muted(TranslatableMessage::text(json_decode($approval->reason, true) ?? $approval->reason))]);
        }

        $this->terminal->closeFrame();
    }

    /**
     * The number of rows a write query would change, counted without changing them. Null when it cannot be counted.
     */
    private function affectedRows(ChatSession $session, PendingApproval $approval): ?string
    {
        if (! $this->isOneOf($approval, [WriteQuery::class])) {
            return null;
        }

        try {
            $count = $session->project->driver_config->agentDriverFor($session->user)->dryRunAffectedRows(
                (string) ($approval->arguments['query'] ?? ''),
                QueryBindings::from($approval->arguments['bindings'] ?? null),
            );
        } catch (Throwable) {
            return null;
        }

        return match ($count) {
            null => null,
            0 => __('ai.artifacts.no_affected_rows'),
            default => trans_choice('ai.artifacts.affected_rows', $count),
        };
    }

    private function rejected(string $reason): Decision
    {
        $this->terminal->line($this->terminal->theme->danger(Theme::FAILURE.' '.$reason));
        $this->terminal->line();

        return Decision::reject($reason);
    }

    /**
     * @param  list<class-string>  $tools
     */
    private function isOneOf(PendingApproval $approval, array $tools): bool
    {
        return in_array($approval->tool, array_map(class_basename(...), $tools), true);
    }
}
