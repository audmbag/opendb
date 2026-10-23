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
                            Colonne
                        </th>
                        <th scope="col" className={cellClassName}>
                            Valeurs NULL
                        </th>
                        <th scope="col" className={cellClassName}>
                            Distinctes
                        </th>
                        <th scope="col" className={cellClassName}>
                            Minimum
                        </th>
                        <th scope="col" className={cellClassName}>
                            Maximum
                        </th>
                        <th
                            scope="col"
                            className={cellClassName}
                            title="Longueur moyenne des textes"
                        >
                            Long. moy.
                        </th>
                        <th
                            scope="col"
                            className={cn(cellClassName, 'border-r-0!')}
                        >
                            Plus fréquentes
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
                                Aucune colonne ne correspond à ce filtre.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default function TableProfile({ ref, profile }: Props) {
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
                description="Profil de données"
                icon={Table2Icon}
                className="w-full max-w-228 gap-0 py-0"
            >
                <EmptyState
                    icon={Table2Icon}
                    title={'Aucune donnée à profiler'}
                    description={`La table existe mais ne contient aucune ligne. ${profile.columns.length > 1 ? `Ses ${formatInteger(profile.columns.length)} colonnes apparaîtront` : 'Sa colonne apparaîtra'} ici dès qu'elle sera alimentée.`}
                />
            </ArtifactFrame>
        );
    }

    return (
        <ArtifactFrame
            ref={ref}
            title={profile.table}
            description="Profil de données"
            icon={Table2Icon}
            actions={
                <Badge>
                    <ClockIcon />
                    {isSampled
                        ? `Échantillon de ${formatRows(profile.sampleSize)}`
                        : formatRows(profile.sampleSize)}
                </Badge>
            }
            className="w-full max-w-228 gap-0 py-0"
        >
            <div className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-2 p-2 pb-0">
                <Stat
                    label="Colonnes"
                    value={formatInteger(columns.length)}
                    hint="toutes analysées"
                />
                <Stat
                    label="Lignes analysées"
                    value={formatInteger(profile.sampleSize)}
                    hint={
                        isSampled
                            ? `sur ${formatInteger(profile.totalRows)} au total`
                            : 'toute la table'
                    }
                />
                <Stat
                    label="Taux de NULL moyen"
                    value={formatPercent(averageNullRate(columns))}
                    hint="sur l'ensemble des colonnes"
                />
                <Stat
                    label="À surveiller"
                    value={
                        <>
                            {columnsNeedingAttention.length > 0 && (
                                <TriangleAlertIcon className="size-4" />
                            )}
                            {formatInteger(columnsNeedingAttention.length)}
                        </>
                    }
                    hint={'colonnes avec plus de 20\u00a0% de NULL'}
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
                            Toutes{' '}
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatInteger(columns.length)}
                            </span>
                        </TabsTrigger>
                        <TabsTrigger value="attention" className="px-3">
                            À surveiller{' '}
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatInteger(columnsNeedingAttention.length)}
                            </span>
                        </TabsTrigger>
                    </TabsList>

                    <label className="relative flex w-60 max-w-full items-center">
                        <span className="sr-only">Filtrer les colonnes</span>
                        <SearchIcon className="pointer-events-none absolute left-2.75 size-3.75 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Filtrer les colonnes..."
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
                        ? `Statistiques calculées sur un échantillon de ${formatRows(profile.sampleSize)}. Les minimums, maximums et valeurs fréquentes peuvent différer sur la table complète.`
                        : `Statistiques calculées sur la table complète (${formatRows(profile.sampleSize)}).`}
                </p>
                <div className="flex flex-wrap gap-3.5">
                    {(
                        [
                            ['low', 'moins de 20\u00a0%'],
                            ['warn', '20 à 50\u00a0%'],
                            ['danger', '50\u00a0% et plus'],
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
