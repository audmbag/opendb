import { useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BrainCircuitIcon,
    LoaderIcon,
    SaveIcon,
    Table2Icon,
} from 'lucide-react';
import { memo, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import CardFrame from '@/components/card-frame';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Switch from '@/components/form/switch';
import { Badge } from '@/components/reui/badge';
import { H5, Muted } from '@/components/typograpfy/typography';
import type { Table } from '@/types/databases';
import type { Project } from '@/types/models/project';
import ProjectSettingsController from '@/wayfinder/actions/App/Http/Controllers/Project/ProjectSettingsController';

type TableRowProps = {
    table: Table;
    onToggle: (tableName: string, enabled: boolean) => void;
};

const TableRow = memo(function TableRow({ table, onToggle }: TableRowProps) {
    const { t, tChoice } = useLaravelReactI18n();

    return (
        <div className="flex items-center justify-between gap-2 border-b border-border p-2 last:border-b-0">
            <div className="flex min-w-0 items-center gap-2">
                <Table2Icon className="size-4 shrink-0" />
                <H5 className="truncate">{table.name}</H5>
                <Badge variant="secondary">{tChoice('tables.counts.columns', table.columns)}</Badge>
            </div>
            <Switch
                className="w-auto"
                orientation="horizontal"
                aria-label={t('projects.tables.make_accessible_to_agent', { table: table.name })}
                checked={table.agentAccessEnabled}
                onCheckedChange={(checked) => onToggle(table.name, checked)}
            />
        </div>
    );
});

/**
 * The tables the agent may see: a disabled table is hidden from its schema and every query citing it is rejected.
 */
export default function AgentTablesProjectSettings() {
    const { t, tChoice } = useLaravelReactI18n();
    const { project } = usePage<{ project: Project }>().props;

    const [tables, setTables] = useState<Table[]>([]);
    const [savedDisabledTables, setSavedDisabledTables] = useState<string[]>(
        [],
    );
    const [search, setSearch] = useState('');

    const loader = useHttp<null, { data: Table[] }>();
    const form = useHttp<{ agentDisabled: string[] }, { message: string }>({
        agentDisabled: [],
    });

    useEffect(() => {
        loader.get(ProjectSettingsController.agentTables.url(project.id), {
            onSuccess: (response) => {
                setTables(response.data);
                setSavedDisabledTables(
                    response.data
                        .filter((table) => !table.agentAccessEnabled)
                        .map((table) => table.name),
                );
            },
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    const disabledTables = useMemo(
        () =>
            tables
                .filter((table) => !table.agentAccessEnabled)
                .map((table) => table.name),
        [tables],
    );

    const filteredTables = useMemo(() => {
        const query = search.trim().toLowerCase();

        return query
            ? tables.filter((table) => table.name.toLowerCase().includes(query))
            : tables;
    }, [search, tables]);

    const toggle = (tableName: string, enabled: boolean) => {
        setTables((current) =>
            current.map((table) =>
                table.name === tableName
                    ? { ...table, agentAccessEnabled: enabled }
                    : table,
            ),
        );
    };

    const toggleAll = (enabled: boolean) => {
        setTables((current) =>
            current.map((table) => ({ ...table, agentAccessEnabled: enabled })),
        );
    };

    form.transform(() => ({ agentDisabled: disabledTables }));

    const save = () => {
        form.put(ProjectSettingsController.updateAgentTables.url(project.id), {
            onSuccess: (response) => {
                toast.success(response.message);
                setSavedDisabledTables(disabledTables);
            },
        });
    };

    const isDirty =
        [...disabledTables].sort().join('\n') !==
        [...savedDisabledTables].sort().join('\n');

    if (loader.processing) {
        return (
            <div className="flex justify-center py-8">
                <LoaderIcon className="animate-spin" />
            </div>
        );
    }

    const connectionError = (
        loader.errors as Record<string, string | undefined>
    ).connection;

    if (connectionError) {
        return (
            <Alert
                variant="destructive"
                title={t('projects.settings.database_unreachable')}
                message={connectionError}
            />
        );
    }

    return (
        <CardFrame
            title={t('projects.settings.agent_tables.title')}
            description={t('projects.settings.agent_tables.description')}
            icon={BrainCircuitIcon}
            innerPadding={false}
            actions={
                <Button
                    icon={SaveIcon}
                    processing={form.processing}
                    disabled={!isDirty}
                    onClick={save}
                >
                    {t('common.save')}
                </Button>
            }
        >
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-2">
                <Input
                    placeholder={t('tables.search_table')}
                    className="w-full max-w-xs"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <div className="flex items-center gap-2">
                    <Muted className="text-xs">
                        {tChoice('projects.settings.agent_tables.accessible', tables.length - disabledTables.length, { total: tables.length })}
                    </Muted>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => toggleAll(true)}
                    >
                        {t('projects.settings.agent_tables.allow_all')}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => toggleAll(false)}
                    >
                        {t('projects.settings.agent_tables.disable_all')}
                    </Button>
                </div>
            </div>
            <div className="max-h-96 overflow-x-hidden overflow-y-auto">
                {filteredTables.map((table) => (
                    <TableRow
                        key={table.name}
                        table={table}
                        onToggle={toggle}
                    />
                ))}
                {filteredTables.length === 0 && (
                    <EmptyState
                        title={t('tables.no_table_found')}
                        icon={Table2Icon}
                        description={
                            search
                                ? t('tables.no_table_match')
                                : t('tables.database_has_no_table')
                        }
                    />
                )}
            </div>
        </CardFrame>
    );
}
