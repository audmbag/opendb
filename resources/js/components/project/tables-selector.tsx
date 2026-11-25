import { useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CopyIcon,
    RefreshCwIcon,
    SparklesIcon,
    Table2Icon,
} from 'lucide-react';
import {
    memo,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useState,
} from 'react';
import type { Ref } from 'react';
import Alert from '@/components/alert';
import { EmptyState } from '@/components/empty-state';
import Input from '@/components/form/input';
import Switch from '@/components/form/switch';
import { Badge } from '@/components/reui/badge';
import { Frame, FramePanel } from '@/components/reui/frame';
import { H5, Muted } from '@/components/typograpfy/typography';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import type { ProjectPartFromRef } from '@/hooks/use-project';
import { cn } from '@/lib/utils';
import { useTempProjectStore } from '@/store/use-project-store';
import type { Table } from '@/types/databases';
import type { Project } from '@/types/models/project';
import ProjectSettingsController from '@/wayfinder/actions/App/Http/Controllers/Project/ProjectSettingsController';

export type TableFormProps = {
    agentDisabled: string[];
    cloningDisabled?: string[];
};

export type TablesSelectorFormRef = ProjectPartFromRef<
    TableFormProps | undefined
>;

const NO_TABLES: Table[] = [];

type TableItemProps = {
    table: Table;
    showHiddenSwitch: boolean;
    onToggleAgentAccess: (tableName: string, enabled: boolean) => void;
    onToggleCloningIncluding: (tableName: string, enabled: boolean) => void;
};

const TableItem = memo(function TableItem({
    table,
    onToggleAgentAccess,
    onToggleCloningIncluding,
    showHiddenSwitch = false,
}: TableItemProps) {
    const { t, tChoice } = useLaravelReactI18n();

    return (
        <div className="flex flex-wrap items-center justify-between border-b border-border p-2 last:border-b-0">
            <div className="flex items-center gap-2">
                <div className="flex items-center gap-1">
                    <Table2Icon className="h-4 w-4" />
                    <H5>{table.name}</H5>
                </div>
                <Separator orientation="vertical" />
                <Badge variant="secondary">
                    {tChoice('tables.counts.columns', table.columns)}
                </Badge>
            </div>
            <div className="flex items-center gap-2">
                {showHiddenSwitch && (
                    <div className="flex items-center">
                        <span
                            title={table.cloningIncludingEnabled ? t('projects.tables.will_be_cloned', { table: table.name }) : t('projects.tables.will_be_skipped', { table: table.name })}
                        >
                            <CopyIcon
                                className={cn(
                                    'h-4 w-4',
                                    table.cloningIncludingEnabled
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            />
                        </span>
                        <Switch
                            className="w-auto"
                            orientation="horizontal"
                            aria-label={t('projects.tables.clone_table', { table: table.name })}
                            checked={table.cloningIncludingEnabled}
                            onCheckedChange={(checked) =>
                                onToggleCloningIncluding(table.name, checked)
                            }
                        />
                    </div>
                )}
                {table.cloningIncludingEnabled &&
                    <>
                        <Separator orientation="vertical" />
                        <div className="flex items-center">
                            <span
                                title={table.agentAccessEnabled ? t('projects.tables.will_be_accessible', { table: table.name }) : t('projects.tables.will_be_inaccessible', { table: table.name })}
                            >
                                <SparklesIcon
                                    className={cn(
                                        'h-4 w-4',
                                        table.agentAccessEnabled
                                            ? 'text-primary'
                                            : 'text-muted-foreground',
                                    )}
                                />
                            </span>
                            <Switch
                                className="w-auto"
                                orientation="horizontal"
                                aria-label={t('projects.tables.make_accessible', { table: table.name })}
                                checked={table.agentAccessEnabled}
                                onCheckedChange={(checked) =>
                                    onToggleAgentAccess(table.name, checked)
                                }
                            />
                        </div>
                    </>
                }
            </div>
        </div>
    );
});

/**
 * The tables of the connection being created, read once the connection is saved: the wizard moves on to this step
 * at once, and the tables of a remote database come in afterwards. Saving another connection reads them again.
 */
function useConnectionTables() {
    const projectId = useTempProjectStore((s) => s.tmpProject?.id);
    const driverConfig = useTempProjectStore((s) => s.tmpProject?.driver_config);
    const loader = useHttp<null, { data: Table[] }>();

    const load = useCallback(() => {
        const { tmpProject } = useTempProjectStore.getState();
        const config = tmpProject?.driver_config;

        if (!tmpProject?.id || !config) {
            return;
        }

        loader.get(ProjectSettingsController.agentTables.url(tmpProject.id), {
            onSuccess: (response) => {
                const { tmpProject: current, setDriverConfig } = useTempProjectStore.getState();

                // A connection saved again in the meantime has its own tables to read.
                if (current?.driver_config === config) {
                    setDriverConfig({ ...config, tables_info: response.data });
                }
            },
        }).catch(() => null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const missing = !!projectId && !!driverConfig && !driverConfig.tables_info;

    useEffect(() => {
        if (missing) {
            load();
        }
    }, [driverConfig, load, missing]);

    const error = (loader.errors as Record<string, string | undefined>).connection;

    return { loading: missing && !error, error: missing ? error : undefined, reload: load };
}

const TableItemSkeleton = memo(function TableItemSkeleton() {
    return (
        <div className="flex items-center justify-between border-b border-border p-2 last:border-b-0">
            <div className="flex items-center gap-2">
                <Skeleton className="size-4" />
                <Skeleton className="h-4 w-36" />
                <Skeleton className="h-5 w-20 rounded-full" />
            </div>
            <Skeleton className="h-5 w-9 rounded-full" />
        </div>
    );
});

const TablesSelector = ({ ref, showHiddenSwitch = false }: { ref?: Ref<TablesSelectorFormRef>, showHiddenSwitch?: boolean }) => {
    const { t: translate, tChoice } = useLaravelReactI18n();
    const [search, setSearch] = useState('');
    const project = usePage<{ project?: Project }>().props.project;

    const tables =
        useTempProjectStore((s) => s.tmpProject?.driver_config?.tables_info) ??
        project?.driver_config?.tables_info ??
        NO_TABLES;

    const { loading, error, reload } = useConnectionTables();

    const query = search.trim().toLowerCase();

    const filteredTables = useMemo(
        () =>
            query
                ? tables.filter((t) => t.name.toLowerCase().includes(query))
                : tables,
        [tables, query],
    );

    const agentEnabledCount = useMemo(
        () => tables.filter((t) => t.agentAccessEnabled).length,
        [tables],
    );

    const cloningEnabledCount = useMemo(
        () => tables.filter((t) => t.cloningIncludingEnabled).length,
        [tables],
    );

    const handleToggle = useCallback(
        (tableName: string, enabled: boolean, type: 'cloning' | 'access') => {
            const { tmpProject, setDriverConfig } = useTempProjectStore.getState();
            const config = tmpProject?.driver_config ?? project?.driver_config;

            if (!config) {
                return;
            }

            setDriverConfig({
                ...config,
                tables_info: (config.tables_info ?? []).map((t): Table =>
                    t.name === tableName ? (type === 'cloning' ? {...t, cloningIncludingEnabled: enabled } : {...t, agentAccessEnabled: enabled}) : t,
                ),
            });
        },
        [project?.driver_config],
    );

    useImperativeHandle(
        ref,
        () => ({
            submit: () => ({
                agentDisabled: tables
                    .filter((t) => !t.agentAccessEnabled)
                    .map((t) => t.name),
                ...(showHiddenSwitch && {
                    cloningDisabled: tables
                        .filter((t) => !t.cloningIncludingEnabled)
                        .map((t) => t.name)
                })
            }),
        }),
        [showHiddenSwitch, tables],
    );

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <Input
                    placeholder={translate('tables.search_table')}
                    className="w-full max-w-xs"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <div className={cn('flex items-center gap-2', loading && 'invisible')}>
                    <Muted>{tChoice('tables.counts.tables', tables.length)}</Muted>
                    <Separator orientation="vertical" />
                    <Muted>
                        {tChoice('projects.tables.accessible_count', agentEnabledCount)}
                    </Muted>
                    {showHiddenSwitch && (
                        <>
                            <Separator orientation="vertical" />
                            <Muted>
                                {tChoice('projects.tables.clonable_count', cloningEnabledCount)}
                            </Muted>
                        </>
                    )}
                </div>
            </div>
            {error && (
                <Alert
                    variant="destructive"
                    title={translate('projects.settings.database_unreachable')}
                    message={error}
                    buttons={[{ label: translate('common.retry'), icon: RefreshCwIcon, variant: 'outline', size: 'sm', onClick: reload }]}
                />
            )}
            <Frame className="max-h-96 overflow-y-auto" aria-busy={loading}>
                <FramePanel className="overflow-visible p-0">
                    {loading && (
                        <>
                            <Muted className="sr-only" role="status">{translate('projects.tables.loading')}</Muted>
                            {[...Array(6).keys()].map((key) => <TableItemSkeleton key={key} />)}
                        </>
                    )}
                    {!loading && filteredTables.map((table) => (
                        <TableItem
                            key={table.name}
                            table={table}
                            onToggleAgentAccess={(name, enabled) => handleToggle(name, enabled, 'access')}
                            onToggleCloningIncluding={(name, enabled) => handleToggle(name, enabled, 'cloning')}
                            showHiddenSwitch={showHiddenSwitch}
                        />
                    ))}
                    {!loading && !error && filteredTables.length === 0 && (
                        <EmptyState
                            title={translate('tables.no_table_found')}
                            icon={Table2Icon}
                            description={
                                query
                                    ? translate('tables.no_table_match')
                                    : translate('tables.no_table_available')
                            }
                        />
                    )}
                </FramePanel>
            </Frame>
        </div>
    );
};

export default TablesSelector;
