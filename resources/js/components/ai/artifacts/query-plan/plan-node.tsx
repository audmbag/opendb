import { Handle, Position } from '@xyflow/react';
import type { NodeProps } from '@xyflow/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { TriangleAlertIcon } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import {
    formatShare,
    nodeMetrics,
    OPERATION_ICONS,
    severityOf,
    showsCostShare,
    STEP_DIRECTIONS,
    worstAlert,
} from '@/components/ai/artifacts/query-plan/format';
import type { StepDirection } from '@/components/ai/artifacts/query-plan/format';
import type { PlanFlowNode } from '@/components/ai/artifacts/query-plan/layout';
import { translateMessage } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PlanAlertSeverity } from '@/types/agent';

export const alertClassName: Record<PlanAlertSeverity, string> = {
    danger: 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400',
    warn: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
};

const accentClassName: Record<PlanAlertSeverity, string> = {
    danger: 'border-l-red-500',
    warn: 'border-l-amber-500',
};

/**
 * A step of the plan: a button that selects it, numbered in execution order, with its figures, its share of the cost
 * and its most serious alert. The arrow keys move to the neighbouring steps.
 * The handles are invisible: data enters at the bottom and leaves at the top.
 */
export default function PlanNode({
    data: { node, plan, step, stepCount, selected, onSelect, onNavigate },
}: NodeProps<PlanFlowNode>) {
    const { t } = useLaravelReactI18n();
    const Icon = OPERATION_ICONS[node.operation];
    const label = translateMessage(t, node.label);
    const subtitle = translateMessage(t, node.subtitle);
    const severity = severityOf(node);
    const alert = worstAlert(node);
    const metrics = nodeMetrics(node, plan);

    const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
        if (STEP_DIRECTIONS.includes(event.key as StepDirection)) {
            event.preventDefault();
            event.stopPropagation();
            onNavigate(node.id, event.key as StepDirection);
        }
    };

    return (
        <>
            <Handle
                type="target"
                position={Position.Bottom}
                className="opacity-0"
                isConnectable={false}
            />
            <button
                type="button"
                data-plan-step={node.id}
                onClick={() => onSelect(node.id)}
                onKeyDown={handleKeyDown}
                aria-pressed={selected}
                aria-label={[
                    t('query_plan.step_label', { step, count: stepCount, label }),
                    subtitle,
                    metrics,
                    alert ? t('query_plan.alert_label', { alert: translateMessage(t, alert.message) }) : null,
                ]
                    .filter(Boolean)
                    .join(', ')}
                className={cn(
                    'nodrag nopan flex w-66 cursor-pointer flex-col gap-1.5 rounded-lg border border-l-4 border-l-border bg-card px-3 py-2.5 text-left shadow-sm transition-[box-shadow,border-color] duration-150 outline-none hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background dark:shadow-none dark:hover:border-foreground/30',
                    severity && accentClassName[severity],
                    selected && 'shadow-md ring-2 ring-primary',
                )}
            >
                <span className="flex min-w-0 items-center gap-2">
                    <span
                        aria-hidden="true"
                        className={cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-semibold text-muted-foreground tabular-nums transition-colors',
                            selected && 'bg-primary text-primary-foreground',
                        )}
                    >
                        {step}
                    </span>
                    <Icon
                        aria-hidden="true"
                        className="size-3.5 shrink-0 text-muted-foreground"
                    />
                    <span className="truncate text-[13px] font-semibold">
                        {label}
                    </span>
                    {node.badge && (
                        <span className="ml-auto inline-flex h-4.5 shrink-0 items-center rounded border px-1.25 text-[10px] font-medium text-muted-foreground">
                            {translateMessage(t, node.badge)}
                        </span>
                    )}
                </span>

                {subtitle && (
                    <span className="truncate font-mono text-xs">
                        {subtitle}
                    </span>
                )}

                <span className="text-xs text-muted-foreground tabular-nums">
                    {metrics}
                </span>

                {showsCostShare(node, plan) && (
                    <span
                        className="flex items-center gap-2"
                        aria-hidden="true"
                    >
                        <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                            <span
                                className={cn(
                                    'block h-full rounded-full',
                                    severity === 'danger'
                                        ? 'bg-red-500'
                                        : severity === 'warn'
                                          ? 'bg-amber-500'
                                          : 'bg-muted-foreground/50',
                                )}
                                style={{
                                    width: `${Math.max((node.costShare ?? 0) * 100, 2)}%`,
                                }}
                            />
                        </span>
                        <span className="text-[11px] text-muted-foreground tabular-nums">
                            {t('query_plan.cost_share', { share: formatShare(node.costShare ?? 0) })}
                        </span>
                    </span>
                )}

                {alert && (
                    <span
                        className={cn(
                            'inline-flex min-h-5 items-center gap-1.25 self-start rounded px-1.5 py-0.5 text-[11px] font-medium',
                            alertClassName[alert.severity],
                        )}
                    >
                        <TriangleAlertIcon
                            aria-hidden="true"
                            className="size-3 shrink-0"
                        />
                        {translateMessage(t, alert.message)}
                    </span>
                )}
            </button>
            <Handle
                type="source"
                position={Position.Top}
                className="opacity-0"
                isConnectable={false}
            />
        </>
    );
}
