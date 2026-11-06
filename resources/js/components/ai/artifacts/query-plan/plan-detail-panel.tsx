import {
    CheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    LightbulbIcon,
    MessageSquareIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import {
    isRead,
    worstAlert,
} from '@/components/ai/artifacts/query-plan/format';
import { alertClassName } from '@/components/ai/artifacts/query-plan/plan-node';
import { Button } from '@/components/ui/button';
import { useConversationPrompt } from '@/hooks/use-conversation-prompt';
import { uppercaseSqlKeywords } from '@/lib/sql';
import { cn } from '@/lib/utils';
import type { PlanAlert, PlanNode, QueryPlan } from '@/types/agent';

type Props = {
    plan: QueryPlan;
    node: PlanNode;
    /**
     * The alert of the previous plan that this step no longer raises, on the « Après » view of a comparison.
     */
    resolvedAlert?: PlanAlert | null;
    /**
     * The number of the step in execution order, and the number of steps.
     */
    step: number;
    stepCount: number;
    /**
     * Select the step run just before or after this one, null for the first and the last step.
     */
    onPrevious: (() => void) | null;
    onNext: (() => void) | null;
};

const toneClassName = {
    danger: 'font-medium text-red-600 dark:text-red-400',
    good: 'font-medium text-green-700 dark:text-green-400',
};

/**
 * The alert of the previous plan resolved on the same table, for a read of the « Après » view.
 */
export function resolvedAlertOf(
    node: PlanNode,
    plan: QueryPlan,
): PlanAlert | null {
    if (
        !plan.previous ||
        !plan.comparison ||
        !isRead(node) ||
        node.table === null
    ) {
        return null;
    }

    const resolved = plan.comparison.resolvedAlerts
        .filter((alert) => alert.table === node.table)
        .map((alert) => alert.code);

    return (
        plan.previous.nodes
            .filter(
                (previous) => isRead(previous) && previous.table === node.table,
            )
            .flatMap((previous) => previous.alerts)
            .find((alert) => resolved.includes(alert.code)) ?? null
    );
}

/**
 * The detail of the selected step: its alert, its explanation, its condition and its figures, with the two questions
 * the artifact can ask the agent. They are the only place where the artifact writes in the conversation.
 */
export default function PlanDetailPanel({
    plan,
    node,
    resolvedAlert,
    step,
    stepCount,
    onPrevious,
    onNext,
}: Props) {
    const { sendPrompt } = useConversationPrompt();
    const alert = worstAlert(node);

    const proposeIndex = () => {
        const columns =
            alert && alert.columns.length > 0
                ? `, colonnes ${alert.columns.join(', ')}`
                : '';

        sendPrompt?.(
            `Propose un index pour l'étape « ${node.label} »${node.table ? ` de ${node.table}` : ''} (alerte ${alert?.code}${columns}).`,
        );
    };

    const explainStep = () =>
        sendPrompt?.(
            `Explique l'étape « ${node.label} » du plan ${plan.planId}.`,
        );

    return (
        <aside
            aria-label="Détail de l'étape"
            className="flex flex-col gap-4 px-5 pt-3.5 pb-5"
        >
            <div className="flex items-center justify-between gap-2">
                <span
                    aria-live="polite"
                    className="text-xs text-muted-foreground tabular-nums"
                >
                    Étape {step} sur {stepCount}
                    <span className="sr-only"> : {node.label}</span>
                </span>
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-7"
                        onClick={onPrevious ?? undefined}
                        disabled={onPrevious === null}
                        aria-label="Étape précédente"
                        title="Étape précédente"
                    >
                        <ChevronLeftIcon />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-7"
                        onClick={onNext ?? undefined}
                        disabled={onNext === null}
                        aria-label="Étape suivante"
                        title="Étape suivante"
                    >
                        <ChevronRightIcon />
                    </Button>
                </div>
            </div>

            <div className="-mt-2">
                <div className="flex flex-wrap items-baseline gap-2">
                    <h3 className="text-[15px] font-semibold">{node.label}</h3>
                    {node.subtitle && (
                        <span className="font-mono text-xs break-all text-muted-foreground">
                            {node.subtitle}
                        </span>
                    )}
                </div>
            </div>

            {resolvedAlert && (
                <div className="flex gap-2 rounded-md bg-green-50 px-2.5 py-2 text-xs font-medium text-green-700 dark:bg-green-950/40 dark:text-green-400">
                    <CheckIcon
                        aria-hidden="true"
                        className="mt-px size-3.5 shrink-0"
                    />
                    <span>Alerte résolue : {resolvedAlert.message}</span>
                </div>
            )}

            {alert && (
                <div
                    className={cn(
                        'flex gap-2 rounded-md px-2.5 py-2 text-xs font-medium',
                        alertClassName[alert.severity],
                    )}
                >
                    <TriangleAlertIcon
                        aria-hidden="true"
                        className="mt-px size-3.5 shrink-0"
                    />
                    <span>{alert.message}</span>
                </div>
            )}

            <p className="text-[13px] leading-[1.55]">{node.explanation}</p>

            {node.condition && (
                <div>
                    <div className="mb-1.5 text-xs font-medium text-muted-foreground">
                        {node.condition.label}
                    </div>
                    <div className="rounded-md border bg-muted/40 px-2.5 py-2 font-mono text-xs wrap-anywhere">
                        {uppercaseSqlKeywords(node.condition.value)}
                    </div>
                </div>
            )}

            {node.details.length > 0 && (
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.75 text-[13px]">
                    {node.details.map((detail, index) => (
                        <div key={index} className="contents">
                            <dt className="text-muted-foreground">
                                {detail.label}
                            </dt>
                            <dd
                                className={cn(
                                    'wrap-anywhere tabular-nums',
                                    detail.mono &&
                                        'font-mono text-xs leading-5',
                                    detail.tone && toneClassName[detail.tone],
                                )}
                            >
                                {detail.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}

            {alert && (
                <div className="flex flex-wrap gap-2 pt-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={proposeIndex}
                        disabled={sendPrompt === null}
                    >
                        <LightbulbIcon />
                        Proposer un index
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={explainStep}
                        disabled={sendPrompt === null}
                    >
                        <MessageSquareIcon />
                        Expliquer cette étape
                    </Button>
                </div>
            )}
        </aside>
    );
}
