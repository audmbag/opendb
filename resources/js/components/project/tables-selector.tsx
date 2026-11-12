import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CopyIcon,
    SparklesIcon,
    Table2Icon,
} from 'lucide-react';
import {
    memo,
    useCallback,
    useImperativeHandle,
    useMemo,
    useState,
} from 'react';
import type { Ref } from 'react';
import { EmptyState } from '@/components/empty-state';
import Input from '@/components/form/input';
import Switch from '@/components/form/switch';
import { Badge } from '@/components/reui/badge';
import { Frame, FramePanel } from '@/components/reui/frame';
import { H5, Muted } from '@/components/typograpfy/typography';
import { Separator } from '@/components/ui/separator';
import type { ProjectPartFromRef } from '@/hooks/use-project';
import { cn } from '@/lib/utils';
import { useTempProjectStore } from '@/store/use-project-store';
import type { Table } from '@/types/databases';
import type { Project } from '@/types/models/project';

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

const TablesSelector = ({ ref, showHiddenSwitch = false }: { ref?: Ref<TablesSelectorFormRef>, showHiddenSwitch?: boolean }) => {
    const { t: translate, tChoice } = useLaravelReactI18n();
    const [search, setSearch] = useState('');
    const project = usePage<{ project?: Project }>().props.project;

    const tables =
        useTempProjectStore((s) => s.tmpProject?.driver_config?.tables_info) ??
        project?.driver_config?.tables_info ??
        NO_TABLES;

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
                <div className="flex items-center gap-2">
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
            <Frame className="max-h-96 overflow-y-auto">
                <FramePanel className="overflow-visible p-0">
                    {filteredTables.map((table) => (
                        <TableItem
                            key={table.name}
                            table={table}
                            onToggleAgentAccess={(name, enabled) => handleToggle(name, enabled, 'access')}
                            onToggleCloningIncluding={(name, enabled) => handleToggle(name, enabled, 'cloning')}
                            showHiddenSwitch={showHiddenSwitch}
                        />
                    ))}
                    {filteredTables.length === 0 && (
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
