import { Head, router } from '@inertiajs/react';
import {
    DatabaseIcon,
    EraserIcon,
    PlusIcon,
    SquareTerminalIcon,
    Table2Icon,
    Trash2Icon,
} from 'lucide-react';
import { Activity, useEffect, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { toast } from 'sonner';
import { formatInteger } from '@/components/ai/artifacts/table-profile/format';
import AlertDialog from '@/components/alert-dialog';
import { EmptyState } from '@/components/empty-state';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import QueryConsole from '@/components/tables/console/query-console';
import TableDesigner from '@/components/tables/designer/table-designer';
import ExportDialog from '@/components/tables/dialogs/export-dialog';
import ImportDialog from '@/components/tables/dialogs/import-dialog';
import TableNameDialog from '@/components/tables/dialogs/table-name-dialog';
import TableList from '@/components/tables/table-list';
import type { TableAction } from '@/components/tables/table-list';
import TableTabs from '@/components/tables/table-tabs';
import type {
    TableStructure,
    TableSummary,
    TableView,
} from '@/components/tables/types';
import { DEFAULT_VIEW, parseViewQuery } from '@/components/tables/view';
import Workspace from '@/components/tables/workspace';
import type { TableFocus } from '@/components/tables/workspace';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import {
    createTableChangesStore,
    pendingChangesCount,
    TableChangesContext,
} from '@/store/use-table-changes-store';
import type { TableChangesStore } from '@/store/use-table-changes-store';
import {
    createTableGridStore,
    TableGridContext,
} from '@/store/use-table-grid-store';
import type { TableGridStore } from '@/store/use-table-grid-store';
import type { Project } from '@/types/models/project';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';
import TableDesignController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableDesignController';

type Props = {
    project: Project;
    tables: TableSummary[];
    table: string | null;
    structure?: TableStructure | null;
};

type TabStores = { changes: TableChangesStore; grid: TableGridStore };

/**
 * A tab of a table owns the stores of its changes and of its grid: they live as long as the tab.
 */
type OpenTab =
    | {
          id: string;
          kind: 'table';
          table: string;
          initialView: TableView;
          focus: TableFocus | null;
          dataVersion: number;
          stores: TabStores;
      }
    | { id: string; kind: 'console'; title: string; initialSql: string };

type Dialog =
    | { type: 'designer'; table: string | null }
    | { type: 'name'; mode: 'rename' | 'duplicate'; table: string }
    | { type: 'export'; tables?: string[] }
    | { type: 'import'; table: string | null }
    | null;

/**
 * The tables opened in tabs are remembered for the next visit of the project.
 */
const tabsKey = (projectId: string) => `opendb:table-tabs:${projectId}`;

let tabSequence = 0;

const newTabId = (kind: OpenTab['kind']) =>
    `${kind}-${Date.now().toString(36)}-${++tabSequence}`;

const tableTab = (
    table: string,
    initialView: TableView = DEFAULT_VIEW,
    focus: Omit<TableFocus, 'nonce'> | null = null,
): OpenTab => {
    const id = newTabId('table');

    return {
        id,
        kind: 'table',
        table,
        initialView,
        focus: focus ? { ...focus, nonce: Date.now() } : null,
        dataVersion: 0,
        stores: {
            changes: createTableChangesStore(id),
            grid: createTableGridStore(),
        },
    };
};

function restoreTabs(
    projectId: string,
    tables: TableSummary[],
    current: string | null,
): OpenTab[] {
    let remembered: string[] = [];

    try {
        remembered = JSON.parse(
            localStorage.getItem(tabsKey(projectId)) ?? '[]',
        );
    } catch {
        // Without storage, only the table of the address opens.
    }

    const names = new Set(tables.map((table) => table.name));
    const opened = [...new Set(remembered)].filter((name) => names.has(name));

    if (current && !opened.includes(current)) {
        opened.push(current);
    }

    return opened.map((name) =>
        tableTab(
            name,
            name === current
                ? parseViewQuery(window.location.search)
                : DEFAULT_VIEW,
        ),
    );
}

/**
 * The Tables tab: the tables of the database in a tree, the ones opened in tabs that keep their state, consoles
 * to run any SQL, and the management of the database by hand: creating and changing tables, importing and exporting.
 */
export default function TablesIndex({
    project,
    tables,
    table,
    structure,
}: Props) {
    const [tabs, setTabs] = useState<OpenTab[]>(() =>
        restoreTabs(project.id, tables, table),
    );
    const [activeId, setActiveId] = useState<string | null>(
        () =>
            tabs.find((tab) => tab.kind === 'table' && tab.table === table)
                ?.id ??
            tabs[0]?.id ??
            null,
    );
    const [dialog, setDialog] = useState<Dialog>(null);
    const [schemaVersion, setSchemaVersion] = useState(0);
    const [addressVersion, setAddressVersion] = useState(0);

    const driver =
        project.driver_config?.driver_id ??
        project.driver_config?.driver?.id ??
        '';
    const tableNames = tables.map((item) => item.name);
    const activeTab = tabs.find((tab) => tab.id === activeId) ?? null;
    const activeTable = activeTab?.kind === 'table' ? activeTab.table : null;

    useEffect(() => {
        try {
            localStorage.setItem(
                tabsKey(project.id),
                JSON.stringify(
                    tabs.flatMap((tab) =>
                        tab.kind === 'table' ? [tab.table] : [],
                    ),
                ),
            );
        } catch {
            // The tabs are only remembered when the browser allows it.
        }
    }, [project.id, tabs]);

    // A console has no address of its own: the address shows the list of tables.
    useEffect(() => {
        if (!activeTable) {
            window.history.replaceState(
                window.history.state,
                '',
                TableBrowserController.index.url({ project: project.id }),
            );
        }
    }, [activeTable, project.id, addressVersion]);

    /**
     * Read the list of tables again, after a change of structure, then give the address back to the shown tab.
     */
    const reloadTables = () => {
        setSchemaVersion((version) => version + 1);
        router.reload({
            only: ['tables'],
            onFinish: () => setAddressVersion((version) => version + 1),
        });
    };

    const openTable = (name: string, focus?: Omit<TableFocus, 'nonce'>) => {
        const existing = tabs.find(
            (tab) => tab.kind === 'table' && tab.table === name,
        );

        if (existing) {
            if (focus) {
                setTabs((current) =>
                    current.map((tab) =>
                        tab.id === existing.id && tab.kind === 'table'
                            ? { ...tab, focus: { ...focus, nonce: Date.now() } }
                            : tab,
                    ),
                );
            }

            setActiveId(existing.id);

            return;
        }

        const tab = tableTab(
            name,
            focus ? { ...DEFAULT_VIEW, filters: focus.filters } : DEFAULT_VIEW,
            focus ?? null,
        );

        setTabs((current) => {
            const index = current.findIndex((item) => item.id === activeId);

            return index < 0
                ? [...current, tab]
                : [
                      ...current.slice(0, index + 1),
                      tab,
                      ...current.slice(index + 1),
                  ];
        });
        setActiveId(tab.id);
    };

    const openConsole = (initialSql = '') => {
        const count = tabs.filter((tab) => tab.kind === 'console').length;
        const tab: OpenTab = {
            id: newTabId('console'),
            kind: 'console',
            title: count === 0 ? 'Console SQL' : `Console SQL ${count + 1}`,
            initialSql,
        };

        setTabs((current) => [...current, tab]);
        setActiveId(tab.id);
    };

    const removeTabs = (ids: string[]) => {
        const remaining = tabs.filter((tab) => !ids.includes(tab.id));

        if (activeId && ids.includes(activeId)) {
            const index = tabs.findIndex((tab) => tab.id === activeId);
            const next =
                tabs.slice(index + 1).find((tab) => !ids.includes(tab.id)) ??
                [...tabs.slice(0, index)]
                    .reverse()
                    .find((tab) => !ids.includes(tab.id));

            setActiveId(next?.id ?? null);
        }

        setTabs(remaining);
    };

    /**
     * Close the tabs, after the user agreed to lose the changes some of them have not applied.
     */
    const closeTabs = async (ids: string[]) => {
        const pending = ids
            .map((id) => tabs.find((tab) => tab.id === id))
            .filter((tab) => tab?.kind === 'table')
            .map((tab) => ({
                tab: tab!,
                count:
                    tab!.kind === 'table'
                        ? pendingChangesCount(tab!.stores.changes.getState())
                        : 0,
            }))
            .filter(({ count }) => count > 0);

        if (pending.length > 0) {
            const count = pending.reduce(
                (total, item) => total + item.count,
                0,
            );
            const names = pending
                .map(({ tab }) => (tab.kind === 'table' ? tab.table : ''))
                .join(', ');
            const confirmed = await AlertDialog.call({
                title: 'Abandonner les modifications ?',
                description: `${count} modification${count > 1 ? 's' : ''} de ${names} ne ${count > 1 ? 'sont' : 'est'} pas appliquée${count > 1 ? 's' : ''}. En fermant, elle${count > 1 ? 's' : ''} sera${count > 1 ? 'ont' : ''} perdue${count > 1 ? 's' : ''}.`,
                icon: Trash2Icon,
                variant: 'destructive',
                confirmText: 'Abandonner',
                cancelText: 'Garder ouvert',
            });

            if (!confirmed) {
                return;
            }
        }

        removeTabs(ids);
    };

    const retarget = (from: string, to: string) =>
        setTabs((current) =>
            current.map((tab) =>
                tab.kind === 'table' && tab.table === from
                    ? { ...tab, table: to }
                    : tab,
            ),
        );

    const touchRows = (name: string | null) =>
        setTabs((current) =>
            current.map((tab) =>
                tab.kind === 'table' && (name === null || tab.table === name)
                    ? { ...tab, dataVersion: tab.dataVersion + 1 }
                    : tab,
            ),
        );

    const runAction = async (action: TableAction, name: string) => {
        const args = { project: project.id, table: encodeURIComponent(name) };

        switch (action) {
            case 'open':
                openTable(name);

                return;
            case 'design':
                setDialog({ type: 'designer', table: name });

                return;
            case 'rename':
            case 'duplicate':
                setDialog({ type: 'name', mode: action, table: name });

                return;
            case 'import':
                setDialog({ type: 'import', table: name });

                return;
            case 'export':
                setDialog({ type: 'export', tables: [name] });

                return;
            case 'truncate': {
                const confirmed = await AlertDialog.call({
                    title: `Vider ${name} ?`,
                    description:
                        'Toutes les lignes de la table seront supprimées et ses identifiants repartiront de 1. Cela ne peut pas être annulé.',
                    icon: EraserIcon,
                    variant: 'destructive',
                    confirmText: 'Vider la table',
                    cancelText: 'Annuler',
                });

                if (!confirmed) {
                    return;
                }

                try {
                    const { deleted } = await requestTableData<{
                        deleted: number;
                    }>('post', TableDesignController.truncate.url(args));

                    toast.success(`${name} est vide`, {
                        description: `${formatInteger(deleted)} ligne${deleted > 1 ? 's' : ''} supprimée${deleted > 1 ? 's' : ''}`,
                    });
                    touchRows(name);
                } catch (exception) {
                    toast.error(`${name} n'a pas été vidée`, {
                        description: (exception as TableRequestError).first(),
                    });
                }

                return;
            }
            case 'drop': {
                const confirmed = await AlertDialog.call({
                    title: `Supprimer ${name} ?`,
                    description:
                        'La table, ses lignes, ses index et ses clés étrangères seront supprimés. Une table référencée par une autre est refusée par la base. Cela ne peut pas être annulé.',
                    icon: Trash2Icon,
                    variant: 'destructive',
                    confirmText: 'Supprimer la table',
                    cancelText: 'Annuler',
                });

                if (!confirmed) {
                    return;
                }

                try {
                    await requestTableData(
                        'delete',
                        TableDesignController.destroy.url(args),
                    );

                    toast.success(`${name} a été supprimée`);
                    // The address must not name the dropped table when the list is read again.
                    window.history.replaceState(
                        window.history.state,
                        '',
                        TableBrowserController.index.url({
                            project: project.id,
                        }),
                    );
                    removeTabs(
                        tabs
                            .filter(
                                (tab) =>
                                    tab.kind === 'table' && tab.table === name,
                            )
                            .map((tab) => tab.id),
                    );
                    reloadTables();
                } catch (exception) {
                    toast.error(`${name} n'a pas été supprimée`, {
                        description: (exception as TableRequestError).first(),
                    });
                }
            }
        }
    };

    const { defaultLayout, onLayoutChanged } = useDefaultLayout({
        id: 'project-tables-layout',
        storage: localStorage,
        panelIds: ['tables-list', 'tables-workspace'],
    });

    const tabItems = tabs.map((tab) => ({
        id: tab.id,
        kind: tab.kind,
        title: tab.kind === 'table' ? tab.table : tab.title,
        changes: tab.kind === 'table' ? tab.stores.changes : null,
    }));

    return (
        <>
            <Head
                title={
                    activeTab
                        ? activeTab.kind === 'table'
                            ? activeTab.table
                            : activeTab.title
                        : 'Tables'
                }
            />

            <ResizablePanelGroup
                orientation="horizontal"
                defaultLayout={defaultLayout}
                onLayoutChanged={onLayoutChanged}
                className="h-full min-h-0 w-full gap-1"
            >
                <ResizablePanel
                    id="tables-list"
                    defaultSize="272px"
                    minSize="200px"
                    maxSize="460px"
                    className="overflow-hidden rounded-lg border bg-sidebar"
                >
                    <TableList
                        projectId={project.id}
                        tables={tables}
                        current={activeTable}
                        openTables={tabs.flatMap((tab) =>
                            tab.kind === 'table' ? [tab.table] : [],
                        )}
                        schemaVersion={schemaVersion}
                        onOpen={openTable}
                        onAction={runAction}
                        onCreateTable={() =>
                            setDialog({ type: 'designer', table: null })
                        }
                        onOpenConsole={() => openConsole()}
                        onImport={() =>
                            setDialog({ type: 'import', table: null })
                        }
                        onExport={() => setDialog({ type: 'export' })}
                    />
                </ResizablePanel>
                <ResizableHandle
                    withHandle
                    className="cursor-col-resize bg-transparent"
                />
                <ResizablePanel
                    id="tables-workspace"
                    className="flex min-w-0 flex-col"
                >
                    <TableTabs
                        tabs={tabItems}
                        activeId={activeId}
                        onSelect={setActiveId}
                        onClose={(id) => closeTabs([id])}
                        onCloseOthers={(id) =>
                            closeTabs(
                                tabs
                                    .filter((tab) => tab.id !== id)
                                    .map((tab) => tab.id),
                            )
                        }
                        onCloseAll={() => closeTabs(tabs.map((tab) => tab.id))}
                    />

                    {tabs.map((tab) => {
                        const selected = tab.id === activeId;

                        return (
                            <Activity
                                key={tab.id}
                                mode={selected ? 'visible' : 'hidden'}
                            >
                                <div className="flex min-h-0 flex-1 flex-col">
                                    {tab.kind === 'console' ? (
                                        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg rounded-t-none border bg-background">
                                            <QueryConsole
                                                projectId={project.id}
                                                driver={driver}
                                                tables={tableNames}
                                                initialSql={tab.initialSql}
                                                onSchemaChanged={reloadTables}
                                            />
                                        </div>
                                    ) : (
                                        (() => {
                                            const summary = tables.find(
                                                (item) =>
                                                    item.name === tab.table,
                                            );

                                            return summary ? (
                                                <TableChangesContext.Provider
                                                    value={tab.stores.changes}
                                                >
                                                    <TableGridContext.Provider
                                                        value={tab.stores.grid}
                                                    >
                                                        <Workspace
                                                            project={project}
                                                            table={tab.table}
                                                            summary={summary}
                                                            initialView={
                                                                tab.initialView
                                                            }
                                                            initialStructure={
                                                                tab.table ===
                                                                table
                                                                    ? structure
                                                                    : null
                                                            }
                                                            active={selected}
                                                            focus={tab.focus}
                                                            schemaVersion={
                                                                schemaVersion
                                                            }
                                                            dataVersion={
                                                                tab.dataVersion
                                                            }
                                                            addressVersion={
                                                                addressVersion
                                                            }
                                                            onOpenTable={
                                                                openTable
                                                            }
                                                            onAction={runAction}
                                                            onOpenConsole={
                                                                openConsole
                                                            }
                                                        />
                                                    </TableGridContext.Provider>
                                                </TableChangesContext.Provider>
                                            ) : (
                                                <div className="flex flex-1 items-center justify-center rounded-lg rounded-t-none border bg-background p-6">
                                                    <EmptyState
                                                        icon={Table2Icon}
                                                        title="Cette table n'existe plus"
                                                        description={`${tab.table} a été renommée ou supprimée hors de cet onglet.`}
                                                        actions={[
                                                            {
                                                                label: "Fermer l'onglet",
                                                                variant:
                                                                    'outline',
                                                                onClick: () =>
                                                                    removeTabs([
                                                                        tab.id,
                                                                    ]),
                                                            },
                                                        ]}
                                                    />
                                                </div>
                                            );
                                        })()
                                    )}
                                </div>
                            </Activity>
                        );
                    })}

                    {tabs.length === 0 && (
                        <div className="flex flex-1 items-center justify-center rounded-lg border bg-background p-6">
                            <EmptyState
                                icon={tables.length ? Table2Icon : DatabaseIcon}
                                title={
                                    tables.length
                                        ? 'Choisissez une table'
                                        : 'Aucune table'
                                }
                                description={
                                    tables.length
                                        ? `La base contient ${formatInteger(tables.length)} table${tables.length > 1 ? 's' : ''}. Chacune s'ouvre dans un onglet, qui garde ses filtres et ses modifications quand vous passez à une autre.`
                                        : 'Cette base de données ne contient encore aucune table.'
                                }
                                actions={[
                                    ...(tables.length
                                        ? [
                                              {
                                                  label: `Ouvrir ${tables[0].name}`,
                                                  variant: 'outline' as const,
                                                  onClick: () =>
                                                      openTable(tables[0].name),
                                              },
                                          ]
                                        : []),
                                    {
                                        label: 'Créer une table',
                                        icon: PlusIcon,
                                        variant: tables.length
                                            ? ('ghost' as const)
                                            : ('default' as const),
                                        onClick: () =>
                                            setDialog({
                                                type: 'designer',
                                                table: null,
                                            }),
                                    },
                                    {
                                        label: 'Console SQL',
                                        icon: SquareTerminalIcon,
                                        variant: 'ghost' as const,
                                        onClick: () => openConsole(),
                                    },
                                ]}
                            />
                        </div>
                    )}
                </ResizablePanel>
            </ResizablePanelGroup>

            {dialog?.type === 'designer' && (
                <TableDesigner
                    projectId={project.id}
                    driver={driver}
                    tables={tableNames}
                    table={dialog.table}
                    onClose={() => setDialog(null)}
                    onDone={(name) => {
                        const previous = dialog.table;

                        setDialog(null);

                        if (previous === null) {
                            openTable(name);
                        } else if (previous !== name) {
                            retarget(previous, name);
                        }

                        reloadTables();
                    }}
                />
            )}

            {dialog?.type === 'name' && (
                <TableNameDialog
                    projectId={project.id}
                    table={dialog.table}
                    mode={dialog.mode}
                    onClose={() => setDialog(null)}
                    onDone={(name) => {
                        setDialog(null);

                        if (dialog.mode === 'rename') {
                            toast.success(
                                `${dialog.table} s'appelle désormais ${name}`,
                            );
                            window.history.replaceState(
                                window.history.state,
                                '',
                                TableBrowserController.index.url({
                                    project: project.id,
                                }),
                            );
                            retarget(dialog.table, name);
                        } else {
                            toast.success(`${name} a été créée`);
                            openTable(name);
                        }

                        reloadTables();
                    }}
                />
            )}

            {dialog?.type === 'export' && (
                <ExportDialog
                    projectId={project.id}
                    tables={tables}
                    initialTables={dialog.tables}
                    onClose={() => setDialog(null)}
                />
            )}

            {dialog?.type === 'import' && (
                <ImportDialog
                    projectId={project.id}
                    tables={tables}
                    table={dialog.table}
                    onClose={() => setDialog(null)}
                    onImported={(names) => {
                        if (names === null) {
                            reloadTables();
                            touchRows(null);
                        } else {
                            names.forEach(touchRows);
                        }
                    }}
                />
            )}
        </>
    );
}
