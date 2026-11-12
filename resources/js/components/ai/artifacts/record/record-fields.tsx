import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowUpRightIcon, SearchIcon } from 'lucide-react';
import { useId, useState } from 'react';
import { formatCell } from '@/components/ai/artifacts/format';
import type { OutgoingRelation, RecordLocation } from '@/components/ai/artifacts/record/format';
import RecordValue from '@/components/ai/artifacts/record/record-value';
import { formatInteger } from '@/components/ai/artifacts/table-profile/format';
import { Badge } from '@/components/reui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { InspectedRecord } from '@/types/agent';

type Props = {
    record: InspectedRecord;
    hideNull: boolean;
    onHideNullChange: (hideNull: boolean) => void;
    onOpen: (location: RecordLocation) => void;
};

/**
 * The columns of the record in the order of the table, filtered by name and optionally without their NULL values.
 */
export default function RecordFields({ record, hideNull, onHideNullChange, onOpen }: Props) {
    const { t } = useLaravelReactI18n();
    const switchId = useId();
    const [search, setSearch] = useState('');

    const entries = Object.entries(record.values);
    const nullCount = entries.filter(([, value]) => value === null).length;
    const foreignColumns = new Map<string, OutgoingRelation>(
        record.outgoing.flatMap((relation) => relation.columns.map((column) => [column, relation] as const)),
    );

    const query = search.trim().toLowerCase();
    const visibleEntries = entries.filter(([column, value]) => (!hideNull || value !== null) && column.toLowerCase().includes(query));

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div className="flex items-baseline gap-2">
                    <span className="text-sm font-medium">{t('ai.record.columns')}</span>
                    <span className="text-sm text-muted-foreground tabular-nums">{formatInteger(entries.length)}</span>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <div className="flex items-center gap-2">
                        <Switch id={switchId} checked={hideNull} onCheckedChange={onHideNullChange} />
                        <Label htmlFor={switchId} className="font-normal text-muted-foreground">
                            {t('ai.record.hide_nulls', { count: formatInteger(nullCount) })}
                        </Label>
                    </div>

                    <label className="relative flex w-56 max-w-full items-center">
                        <span className="sr-only">{t('ai.record.filter_columns')}</span>
                        <SearchIcon className="pointer-events-none absolute left-2.75 size-3.75 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('ai.record.filter_columns_placeholder')}
                            className="pl-8.5"
                        />
                    </label>
                </div>
            </div>

            <dl>
                {visibleEntries.map(([column, value]) => {
                    const relation = foreignColumns.get(column);

                    return (
                        <div
                            key={column}
                            className="grid odd:bg-muted/50 grid-cols-[minmax(8rem,12rem)_minmax(0,1fr)] items-start gap-x-5 gap-y-1.5 border-t px-4 py-1.5 hover:bg-muted/40 @max-md:grid-cols-1"
                        >
                            <dt className="flex min-h-6 min-w-0 items-center gap-1.5">
                                <span className="truncate text-sm font-medium" title={column}>
                                    {column}
                                </span>
                                {record.primaryKey.includes(column) && (
                                    <Badge variant="outline" className="tracking-wide text-muted-foreground">
                                        PK
                                    </Badge>
                                )}
                                {relation && (
                                    <Badge variant="outline" className="tracking-wide text-muted-foreground">
                                        FK
                                    </Badge>
                                )}
                            </dt>
                            <dd className="flex min-h-6 min-w-0 items-center">
                                {relation ? (
                                    <button
                                        type="button"
                                        className="group inline-flex min-w-0 items-center gap-1.5 rounded-sm text-left text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                        aria-label={t('ai.record.open', { table: relation.table, key: formatCell(value) })}
                                        onClick={() => onOpen({ table: relation.table, key: relation.key })}
                                    >
                                        <span className="tabular-nums underline-offset-4 group-hover:underline">{formatCell(value)}</span>
                                        <span className="truncate text-muted-foreground">{relation.table}</span>
                                        <ArrowUpRightIcon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                    </button>
                                ) : (
                                    <RecordValue value={value} />
                                )}
                            </dd>
                        </div>
                    );
                })}
            </dl>

            {visibleEntries.length === 0 && (
                <div className="border-t px-6 py-10 text-center text-sm text-muted-foreground">{t('ai.record.no_column_match')}</div>
            )}
        </>
    );
}
