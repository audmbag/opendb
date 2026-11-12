import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowRightIcon, ChevronRightIcon } from 'lucide-react';
import { useId } from 'react';
import type { ReactNode } from 'react';
import { formatInteger, formatLength, formatPercent, formatValue } from '@/components/ai/artifacts/table-profile/format';
import type { DerivedColumn } from '@/components/ai/artifacts/table-profile/format';
import NullRate from '@/components/ai/artifacts/table-profile/null-rate';
import { TopValuesChip, TopValuesList } from '@/components/ai/artifacts/table-profile/top-values';
import CardFrame from '@/components/card-frame';
import { Badge } from '@/components/reui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    column: DerivedColumn;
    sampleSize: number;
    open: boolean;
    onToggle: () => void;
};

/**
 * The cell padding shared by the header and the rows of the columns table: 24px between columns, wider margins on the edges.
 */
export const cellClassName = 'px-3 first:pl-4 last:pr-6 text-nowrap';

/**
 * The widths of the columns of the table, shared by the profile and its skeleton.
 */
export function ProfileColumnWidths() {
    return (
        <colgroup>
            <col className="w-[19%]" />
            <col className="w-32" />
            <col className="w-28" />
            <col className="w-[13%]" />
            <col className="w-[13%]" />
            <col className="w-26" />
            <col className="w-[17%]" />
        </colgroup>
    );
}

/**
 * A bound of a column: its smallest or largest value, or for a text column the length of its shortest or longest value.
 */
function Bound({ value, length, label }: { value: DerivedColumn['min']; length: number | null; label: string }) {
    const { t } = useLaravelReactI18n();

    if (length !== null) {
        return (
            <td className={cellClassName}>
                <div className="truncate text-sm tabular-nums" title={t(label, { length: formatInteger(length) })}>
                    {formatInteger(length)} <span className="text-xs text-muted-foreground">{t('ai.profile.chars_short')}</span>
                </div>
            </td>
        );
    }

    return (
        <td className={cellClassName}>
            <div className={cn('truncate text-sm', value === null && 'text-muted-foreground')} title={formatValue(value)}>
                {formatValue(value)}
            </div>
        </td>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode; mono?: boolean }) {
    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={cn('wrap-anywhere tabular-nums')}>{children}</dd>
        </>
    );
}

function lengthDetail(column: DerivedColumn, t: (key: string, replacements?: Record<string, string>) => string): string {
    if (column.averageLength === null) {
        return column.kind === 'text' ? '–' : t('ai.profile.not_applicable');
    }

    return t('ai.profile.chars', { length: formatLength(column.averageLength) });
}

/**
 * A column of the profile, which unfolds the frequent values and the details of its statistics in the row below.
 */
export default function ColumnRow({ column, sampleSize, open, onToggle }: Props) {
    const { t } = useLaravelReactI18n();
    const detailId = useId();

    return (
        <>
            <tr
                className={cn(
                    'border-t odd:bg-muted/70 [&>td]:h-10 [&>td]:border-r [&>td]:border-border [&>td]:py-1',
                    !open && 'hover:bg-muted/40',
                )}
            >
                <td className={cellClassName}>
                    <div className="flex min-w-0 items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            className="size-7 text-muted-foreground"
                            aria-expanded={open}
                            aria-controls={detailId}
                            aria-label={t('ai.profile.column_detail', { column: column.name })}
                            onClick={onToggle}
                        >
                            <ChevronRightIcon
                                className={cn(
                                    'transition-transform duration-150',
                                    open && 'rotate-90',
                                )}
                            />
                        </Button>
                        <div className="flex min-w-0 flex-col gap-1">
                            <div className="flex items-center gap-1.5">
                                <span className="truncate text-sm font-medium">
                                    {column.name}
                                </span>
                                {column.key && (
                                    <Badge
                                        variant="outline"
                                        size="sm"
                                        className="tracking-wide text-muted-foreground"
                                    >
                                        {column.key}
                                    </Badge>
                                )}
                            </div>
                            <div
                                className="truncate text-xs text-muted-foreground"
                                title={column.type}
                            >
                                {column.type}
                            </div>
                        </div>
                    </div>
                </td>

                <td className={cellClassName}>
                    <NullRate
                        rate={column.nullRate}
                        severity={column.severity}
                    />
                </td>

                <td className={cellClassName}>
                    <div className="flex items-center gap-1">
                        {column.cardinality ? (
                            <Badge variant="outline">
                                {t(`ai.profile.cardinality.${column.cardinality}`)}
                            </Badge>
                        ) : (
                            column.distinctRate !== null && (
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {formatPercent(column.distinctRate)}
                                </span>
                            )
                        )}
                        <ArrowRightIcon className="size-4 shrink-0 text-muted-foreground" />
                        <span className="text-sm tabular-nums">
                            {column.distinctCount === null
                                ? '–'
                                : formatInteger(column.distinctCount)}
                        </span>
                    </div>
                </td>

                <Bound
                    value={column.min}
                    length={column.minLength}
                    label="ai.profile.min_length"
                />
                <Bound
                    value={column.max}
                    length={column.maxLength}
                    label="ai.profile.max_length"
                />

                <td
                    className={cn(
                        cellClassName,
                        'text-right text-sm tabular-nums',
                        column.averageLength === null &&
                            'text-muted-foreground',
                    )}
                >
                    {formatLength(column.averageLength)}
                </td>

                <td className={cn(cellClassName, 'border-r-0!')}>
                    <div className="flex min-w-0 items-center gap-1.5">
                        <TopValuesChip column={column} />
                    </div>
                </td>
            </tr>

            {open && (
                <tr>
                    <td colSpan={7} className="p-0">
                        <div
                            id={detailId}
                            className="grid grid-cols-2 gap-2 p-2"
                        >
                            <CardFrame title={t('ai.profile.top_values')}>
                                <TopValuesList column={column} />
                            </CardFrame>
                            <CardFrame title={t('ai.profile.column_details')}>
                                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-5 gap-y-2 text-sm">
                                    <Detail label={t('ai.profile.type')}>{column.type}</Detail>
                                    <Detail label={t('ai.profile.filled')}>
                                        {t('cloning.ui.count_of', {
                                            count: formatInteger(column.filled),
                                            total: formatInteger(sampleSize),
                                        })}
                                    </Detail>
                                    <Detail label="NULL">
                                        {formatInteger(column.nullCount)} (
                                        {formatPercent(column.nullRate)})
                                    </Detail>
                                    <Detail label={t('ai.profile.distinct')}>
                                        {column.distinctCount === null
                                            ? t('ai.profile.not_computed')
                                            : column.distinctRate === null
                                              ? formatInteger(column.distinctCount)
                                              : t('ai.profile.distinct_rate', {
                                                    count: formatInteger(column.distinctCount),
                                                    rate: formatPercent(column.distinctRate),
                                                })}
                                    </Detail>
                                    {column.minLength !== null &&
                                    column.maxLength !== null ? (
                                        <Detail label={t('ai.profile.min_max_length')}>
                                            {t('ai.profile.chars_range', {
                                                min: formatInteger(column.minLength),
                                                max: formatInteger(column.maxLength),
                                            })}
                                        </Detail>
                                    ) : (
                                        <Detail label="Min / Max">
                                            {formatValue(column.min)}&nbsp; /
                                            &nbsp;
                                            {formatValue(column.max)}
                                        </Detail>
                                    )}
                                    <Detail label={t('ai.profile.average_length')}>
                                        {lengthDetail(column, t)}
                                    </Detail>
                                </dl>
                            </CardFrame>
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}
