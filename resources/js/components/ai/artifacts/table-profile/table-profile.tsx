import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ClockIcon, SearchIcon, Table2Icon, TriangleAlertIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode, Ref } from 'react';
import ArtifactFrame from '@/components/ai/artifacts/artifact-frame';
import ColumnRow, { cellClassName, ProfileColumnWidths } from '@/components/ai/artifacts/table-profile/column-row';
import { averageNullRate, deriveColumn, formatInteger, formatPercent, formatRows } from '@/components/ai/artifacts/table-profile/format';
import type { DerivedColumn } from '@/components/ai/artifacts/table-profile/format';
import { severityBarClassName } from '@/components/ai/artifacts/table-profile/null-rate';
import CardFrame from '@/components/card-frame';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/reui/badge';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type { TableProfile as TableProfileData } from '@/types/agent';

type Props = {
    ref?: Ref<HTMLDivElement>;
    profile: TableProfileData;
};

type Tab = 'all' | 'attention';

function Stat({ label, value, hint, className }: { label: string; value: ReactNode; hint: string; className?: string }) {
    return (
        <CardFrame>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className={cn('flex items-center gap-2 text-[22px] font-semibold tracking-tight tabular-nums', className)}>{value}</div>
            <div className="text-xs text-muted-foreground">{hint}</div>
        </CardFrame>
    );
}

function ColumnsTable({ columns, sampleSize }: { columns: DerivedColumn[]; sampleSize: number }) {
    const { t } = useLaravelReactI18n();
    const [openColumn, setOpenColumn] = useState<string | null>(null);

    return (
        <div className="overflow-x-auto border-t">
            <table className="w-full min-w-225 text-left">
                <ProfileColumnWidths />
                <thead>
                    <tr className="h-10 text-sm [&>th]:border-r [&>th]:border-border [&>th]:font-medium">
                        <th
                            scope="col"
                            className={cn(cellClassName, 'first:pl-13')}
                        >
                            {t('ai.profile.headers.column')}
                        </th>
                        <th scope="col" className={cellClassName}>
                            {t('ai.profile.headers.nulls')}
                        </th>
                        <th scope="col" className={cellClassName}>
                            {t('ai.profile.distinct')}
                        </th>
                        <th scope="col" className={cellClassName}>
                            {t('ai.profile.headers.min')}
                        </th>
                        <th scope="col" className={cellClassName}>
                            {t('ai.profile.headers.max')}
                        </th>
                        <th
                            scope="col"
                            className={cellClassName}
                            title={t('ai.profile.headers.average_length_title')}
                        >
                            {t('ai.profile.headers.average_length')}
                        </th>
                        <th
                            scope="col"
                            className={cn(cellClassName, 'border-r-0!')}
                        >
                            {t('ai.profile.headers.top_values')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {columns.map((column) => (
                        <ColumnRow
                            key={column.name}
                            column={column}
                            sampleSize={sampleSize}
                            open={openColumn === column.name}
                            onToggle={() =>
                                setOpenColumn(
                                    openColumn === column.name
                                        ? null
                                        : column.name,
                                )
                            }
                        />
                    ))}

                    {columns.length === 0 && (
                        <tr className="border-t">
                            <td
                                colSpan={7}
                                className="px-6 py-10 text-center text-[13px] text-muted-foreground"
                            >
                                {t('ai.record.no_column_match')}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default function TableProfile({ ref, profile }: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const [tab, setTab] = useState<Tab>('all');
    const [search, setSearch] = useState('');

    const columns = useMemo(() => profile.columns.map((column) => deriveColumn(column, profile.sampleSize)), [profile]);
    const columnsNeedingAttention = columns.filter((column) => column.needsAttention);
    const isSampled = profile.totalRows > profile.sampleSize;

    const visibleColumns = (onlyNeedingAttention: boolean): DerivedColumn[] => {
        const query = search.trim().toLowerCase();

        return (onlyNeedingAttention ? columnsNeedingAttention : columns).filter((column) => column.name.toLowerCase().includes(query));
    };

    if (profile.sampleSize === 0) {
        return (
            <ArtifactFrame
                ref={ref}
                title={profile.table}
                description={t('ai.profile.title')}
                icon={Table2Icon}
                className="w-full max-w-228 gap-0 py-0"
            >
                <EmptyState
                    icon={Table2Icon}
                    title={t('ai.profile.empty')}
                    description={tChoice('ai.profile.empty_description', profile.columns.length, { count: formatInteger(profile.columns.length) })}
                />
            </ArtifactFrame>
        );
    }

    return (
        <ArtifactFrame
            ref={ref}
            title={profile.table}
            description={t('ai.profile.title')}
            icon={Table2Icon}
            actions={
                <Badge>
                    <ClockIcon />
                    {isSampled
                        ? t('ai.profile.sample_of', { rows: formatRows(profile.sampleSize) })
                        : formatRows(profile.sampleSize)}
                </Badge>
            }
            className="w-full max-w-228 gap-0 py-0"
        >
            <div className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-2 p-2 pb-0">
                <Stat
                    label={t('ai.record.columns')}
                    value={formatInteger(columns.length)}
                    hint={t('ai.profile.all_analyzed')}
                />
                <Stat
                    label={t('ai.profile.analyzed_rows')}
                    value={formatInteger(profile.sampleSize)}
                    hint={
                        isSampled
                            ? t('ai.profile.of_total', { total: formatInteger(profile.totalRows) })
                            : t('ai.profile.whole_table')
                    }
                />
                <Stat
                    label={t('ai.profile.average_null_rate')}
                    value={formatPercent(averageNullRate(columns))}
                    hint={t('ai.profile.all_columns')}
                />
                <Stat
                    label={t('ai.profile.to_watch')}
                    value={
                        <>
                            {columnsNeedingAttention.length > 0 && (
                                <TriangleAlertIcon className="size-4" />
                            )}
                            {formatInteger(columnsNeedingAttention.length)}
                        </>
                    }
                    hint={t('ai.profile.to_watch_hint', { rate: formatPercent(20) })}
                    className={cn(
                        columnsNeedingAttention.length > 0 &&
                            'text-amber-700 dark:text-amber-400',
                    )}
                />
            </div>

            <Tabs
                value={tab}
                onValueChange={(value) => setTab(value as Tab)}
                className="gap-0"
            >
                <div className="flex flex-wrap items-center justify-between gap-3 px-2 py-3.5">
                    <TabsList>
                        <TabsTrigger value="all" className="px-3">
                            {t('ai.profile.all')}{' '}
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatInteger(columns.length)}
                            </span>
                        </TabsTrigger>
                        <TabsTrigger value="attention" className="px-3">
                            {t('ai.profile.to_watch')}{' '}
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatInteger(columnsNeedingAttention.length)}
                            </span>
                        </TabsTrigger>
                    </TabsList>

                    <label className="relative flex w-60 max-w-full items-center">
                        <span className="sr-only">{t('ai.record.filter_columns')}</span>
                        <SearchIcon className="pointer-events-none absolute left-2.75 size-3.75 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('ai.record.filter_columns_placeholder')}
                            className="pl-8.5 md:text-[13px]"
                        />
                    </label>
                </div>

                <TabsContent value="all">
                    <ColumnsTable
                        columns={visibleColumns(false)}
                        sampleSize={profile.sampleSize}
                    />
                </TabsContent>
                <TabsContent value="attention">
                    <ColumnsTable
                        columns={visibleColumns(true)}
                        sampleSize={profile.sampleSize}
                    />
                </TabsContent>
            </Tabs>

            <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t px-6 py-3.5 text-xs text-muted-foreground">
                <p className="max-w-115">
                    {isSampled
                        ? t('ai.profile.sampled_footer', { rows: formatRows(profile.sampleSize) })
                        : t('ai.profile.full_footer', { rows: formatRows(profile.sampleSize) })}
                </p>
                <div className="flex flex-wrap gap-3.5">
                    {(
                        [
                            ['low', t('ai.profile.severity.low', { rate: formatPercent(20) })],
                            ['warn', t('ai.profile.severity.warn', { from: formatInteger(20), to: formatPercent(50) })],
                            ['danger', t('ai.profile.severity.danger', { rate: formatPercent(50) })],
                        ] as const
                    ).map(([severity, label]) => (
                        <span
                            key={severity}
                            className="inline-flex items-center gap-1.5"
                        >
                            <i className={cn('inline-block h-1.5 w-3.5 rounded-full', severityBarClassName[severity],)} aria-hidden="true" />
                            {label}
                        </span>
                    ))}
                </div>
            </div>
        </ArtifactFrame>
    );
}
