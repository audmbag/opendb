import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ScanTextIcon } from 'lucide-react';
import { formatInteger, formatPercent, formatValue } from '@/components/ai/artifacts/table-profile/format';
import type { DerivedColumn } from '@/components/ai/artifacts/table-profile/format';
import { EmptyState } from '@/components/empty-state';

type Props = {
    column: DerivedColumn;
};

/**
 * The most frequent value of a column in its row, followed by the number of the other frequent values.
 */
export function TopValuesChip({ column }: Props) {
    const { t } = useLaravelReactI18n();
    const [first, ...others] = column.topValues;

    if (column.distinctCount === null) {
        return <span className="text-sm text-muted-foreground">–</span>;
    }

    if (!column.hasRepetition) {
        return <span className="truncate text-sm text-muted-foreground">{column.isUnique ? t('ai.profile.no_repetition') : t('ai.profile.no_dominant_value')}</span>;
    }

    return (
        <>
            <span className="inline-flex h-5.5 min-w-0 items-center gap-1.5 rounded-md bg-muted px-1.75 text-sm" title={formatValue(first.value)}>
                <span className="truncate">{formatValue(first.value)}</span>
                <span className="shrink-0 text-muted-foreground tabular-nums">{formatInteger(first.count)}</span>
            </span>
            {others.length > 0 && <span className="shrink-0 text-sm text-muted-foreground">+{others.length}</span>}
        </>
    );
}

/**
 * The frequent values of a column in its detail, each with its share of the filled rows and a bar relative to the most frequent one.
 */
export function TopValuesList({ column }: Props) {
    const { t } = useLaravelReactI18n();

    if (column.distinctCount === null) {
        return (
            <EmptyState
                className="p-0"
                description={t('ai.profile.not_computed')}
                icon={ScanTextIcon}
            />
        );
    }

    if (!column.hasRepetition) {
        return <EmptyState className="p-0" description={t('ai.profile.all_unique')} icon={ScanTextIcon} />;
    }

    const highest = column.topValues[0].count;

    return (
        <ul className="flex flex-col gap-2.5">
            {column.topValues.map((top, index) => (
                <li key={index}>
                    <div className="flex items-baseline justify-between gap-3 text-xs">
                        <span className="truncate" title={formatValue(top.value)}>
                            {formatValue(top.value)}
                        </span>
                        <span className="shrink-0 text-muted-foreground tabular-nums">
                            {formatInteger(top.count)} · {formatPercent((top.count / column.filled) * 100)}
                        </span>
                    </div>
                    <div className="mt-1.25 h-1 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                        <div className="h-full rounded-full bg-foreground/85" style={{ width: `${Math.max((top.count / highest) * 100, 2)}%` }} />
                    </div>
                </li>
            ))}
        </ul>
    );
}
