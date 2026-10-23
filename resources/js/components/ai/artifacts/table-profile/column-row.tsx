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
    if (length !== null) {
        return (
            <td className={cellClassName}>
                <div className="truncate text-sm tabular-nums" title={`Longueur ${label} : ${formatInteger(length)} caractères`}>
                    {formatInteger(length)} <span className="text-xs text-muted-foreground">car.</span>
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

function lengthDetail(column: DerivedColumn): string {
    if (column.averageLength === null) {
        return column.kind === 'text' ? '–' : 'Non applicable (type non textuel)';
    }

    return `${formatLength(column.averageLength)} caractères`;
}

/**
 * A column of the profile, which unfolds the frequent values and the details of its statistics in the row below.
 */
export default function ColumnRow({ column, sampleSize, open, onToggle }: Props) {
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
                            aria-label={`Détail de la colonne ${column.name}`}
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
                                {column.cardinality}
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
                    label="minimale"
                />
                <Bound
                    value={column.max}
                    length={column.maxLength}
                    label="maximale"
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
                            <CardFrame title={'Valeurs les plus fréquentes'}>
                                <TopValuesList column={column} />
                            </CardFrame>
                            <CardFrame title={'Détails de la colonne'}>
                                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-5 gap-y-2 text-sm">
                                    <Detail label="Type">{column.type}</Detail>
                                    <Detail label="Renseignées">
                                        {formatInteger(column.filled)} sur{' '}
                                        {formatInteger(sampleSize)}
                                    </Detail>
                                    <Detail label="NULL">
                                        {formatInteger(column.nullCount)} (
                                        {formatPercent(column.nullRate)})
                                    </Detail>
                                    <Detail label="Distinctes">
                                        {column.distinctCount === null
                                            ? 'Non calculées pour ce type de colonne'
                                            : `${formatInteger(column.distinctCount)}${column.distinctRate === null ? '' : ` (${formatPercent(column.distinctRate)} des valeurs renseignées)`}`}
                                    </Detail>
                                    {column.minLength !== null &&
                                    column.maxLength !== null ? (
                                        <Detail label="Longueur min / max">
                                            {formatInteger(column.minLength)} /{' '}
                                            {formatInteger(column.maxLength)}{' '}
                                            caractères
                                        </Detail>
                                    ) : (
                                        <Detail label="Min / Max">
                                            {formatValue(column.min)}&nbsp; /
                                            &nbsp;
                                            {formatValue(column.max)}
                                        </Detail>
                                    )}
                                    <Detail label="Long. moyenne">
                                        {lengthDetail(column)}
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
