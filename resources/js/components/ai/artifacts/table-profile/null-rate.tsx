import { formatPercent } from '@/components/ai/artifacts/table-profile/format';
import type { NullSeverity } from '@/components/ai/artifacts/table-profile/format';
import { cn } from '@/lib/utils';

type Props = {
    rate: number;
    severity: NullSeverity;
};

export const severityTextClassName: Record<NullSeverity, string> = {
    none: 'text-muted-foreground',
    low: 'text-foreground',
    warn: 'font-medium text-amber-700 dark:text-amber-400',
    danger: 'font-medium text-red-600 dark:text-red-400',
};

export const severityBarClassName: Record<NullSeverity, string> = {
    none: 'bg-muted-foreground/50',
    low: 'bg-muted-foreground/50',
    warn: 'bg-amber-500',
    danger: 'bg-red-500',
};

/**
 * The rate of NULL of a column, written and drawn as a bar coloured by its severity.
 */
export default function NullRate({ rate, severity }: Props) {
    return (
        <div className="flex flex-col gap-1.5">
            <span className={cn('text-[13px] tabular-nums', severityTextClassName[severity])}>{formatPercent(rate)}</span>
            <div className="h-1.5 w-20 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                <div className={cn('h-full rounded-full', severityBarClassName[severity])} style={{ width: `${rate > 0 ? Math.max(rate, 3) : 0}%` }} />
            </div>
        </div>
    );
}
