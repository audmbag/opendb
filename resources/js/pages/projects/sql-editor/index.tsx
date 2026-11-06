import { Head, router } from '@inertiajs/react';
import type { EditorView } from '@uiw/react-codemirror';
import {
    FileCodeIcon,
    FilePlusIcon,
    LoaderCircleIcon,
    Trash2Icon,
} from 'lucide-react';
import { Activity, useEffect, useRef, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { toast } from 'sonner';
import AlertDialog from '@/components/alert-dialog';
import { EmptyState } from '@/components/empty-state';
import FilesPanel from '@/components/sql-editor/files-panel';
import HistoryPanel from '@/components/sql-editor/history-panel';
import SqlFileTab from '@/components/sql-editor/sql-file-tab';
import type {
    SaveStatus,
    SqlFile,
    SqlFileSummary,
} from '@/components/sql-editor/types';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import TableList from '@/components/tables/table-list';
import TableTabs from '@/components/tables/table-tabs';
import type { TableSummary } from '@/components/tables/types';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { plainIdentifier } from '@/lib/sql';
import { cn } from '@/lib/utils';
import type { Project } from '@/types/models/project';
import SqlEditorQueryController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlEditorQueryController';
import SqlFileController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlFileController';
import TableAssistantController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableAssistantController';

type Props = {
    project: Project;
    tables: TableSummary[];
    files: SqlFileSummary[];
    /**
     * The columns of each table, deferred: the editor completes only the tables until they arrive.
     */
    completion?: Record<string, string[]>;
};

/**
 * A tab keeps its id when its file is renamed, so that the console keeps its results.
 */
type OpenTab = {
    id: string;
    name: string;
    /**
     * The file as the editor just created it, not read again when its tab opens.
     */
    initial?: SqlFile;
};

type Explorer = 'files' | 'tables' | 'history';

const EXPLORERS: Explorer[] = ['files', 'tables', 'history'];

/**
 * Without a remembered list, the first files are opened: a project with many scripts does not read them all.
 */
const OPENED_BY_DEFAULT = 5;

const tabsKey = (projectId: string) => `opendb:sql-editor-tabs:${projectId}`;
const explorerKey = 'opendb:sql-editor-explorer';

let tabCount = 0;
const newTab = (name: string, initial?: SqlFile): OpenTab => ({
    id: `file-${Date.now()}-${tabCount++}`,
    name,
    initial,
});

/**
 * The names of the files opened the last time and the one shown, kept by the browser: never their content.
 */
function restoreTabs(
    projectId: string,
    files: SqlFileSummary[],
): { tabs: OpenTab[]; active: string | null } {
    const names = new Set(files.map((file) => file.name));

    try {
        const stored = JSON.parse(
            localStorage.getItem(tabsKey(projectId)) ?? 'null',
        ) as { open: string[]; active: string | null } | null;

        if (stored && Array.isArray(stored.open)) {
            const open = stored.open.filter((name) => names.has(name));

            return {
                tabs: open.map((name) => newTab(name)),
                active:
                    stored.active && open.includes(stored.active)
                        ? stored.active
                        : (open[0] ?? null),
            };
        }
    } catch {
        // Nothing remembered, or the browser refuses the storage: the first files are opened.
    }

    const open = files.slice(0, OPENED_BY_DEFAULT).map((file) => file.name);

    return { tabs: open.map((name) => newTab(name)), active: open[0] ?? null };
}

function SaveIndicator({ status }: { status: SaveStatus | undefined }) {
    if (status === 'saving' || status === 'pending') {
        return (
            <LoaderCircleIcon
                className={cn(
                    'size-3 shrink-0 text-muted-foreground',
                    status === 'saving' &&
                        'animate-spin motion-reduce:animate-none',
                )}
                aria-label={
                    status === 'saving'
                        ? 'Enregistrement…'
                        : 'Modifications non enregistrées'
                }
            />
        );
    }

    if (status === 'conflict' || status === 'error') {
        return (
            <span
                className="size-1.5 shrink-0 rounded-full bg-destructive"
                aria-label="Non enregistré"
            />
        );
    }

    return null;
}

/**
 * The SQL editor: the .sql files of the project opened in tabs, each one a console of the Tables tab whose
 * script is saved to its file as the user types, next to the tables of the database to insert in the script.
 */
export default function SqlEditorIndex({
    project,
    tables,
    files: initialFiles,
    completion,
}: Props) {
    const [files, setFiles] = useState(initialFiles);
    const [{ tabs, activeId }, setOpen] = useState(() => {
        const restored = restoreTabs(project.id, initialFiles);

        return {
            tabs: restored.tabs,
            activeId:
                restored.tabs.find((tab) => tab.name === restored.active)?.id ??
                null,
        };
    });
    const [statuses, setStatuses] = useState<Record<string, SaveStatus>>({});
    const [renaming, setRenaming] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);
    const [schemaVersion, setSchemaVersion] = useState(0);
    const [explorer, setExplorer] = useState<Explorer>(() => {
        try {
            const stored = localStorage.getItem(explorerKey) as Explorer;

            return EXPLORERS.includes(stored) ? stored : 'files';
        } catch {
            return 'files';
        }
    });
    const [historyVersion, setHistoryVersion] = useState(0);
    const views = useRef(new Map<string, EditorView>());
    const flushers = useRef(new Map<string, () => Promise<void>>());

    const driver =
        project.driver_config?.driver_id ??
        project.driver_config?.driver?.id ??
        '';
    const tableNames = tables.map((item) => item.name);
    const runUrl = SqlEditorQueryController.run.url({ project: project.id });
    const exportUrl = SqlEditorQueryController.exportMethod.url({
        project: project.id,
    });
    const explainUrl = SqlEditorQueryController.explain.url({
        project: project.id,
    });
    const reviewUrl = TableAssistantController.reviewQuery.url({
        project: project.id,
    });
    const activeTab = tabs.find((tab) => tab.id === activeId) ?? null;

    const setTabs = (update: (current: OpenTab[]) => OpenTab[]) =>
        setOpen((state) => ({ ...state, tabs: update(state.tabs) }));
    const setActiveId = (id: string | null) =>
        setOpen((state) => ({ ...state, activeId: id }));

    useEffect(() => {
        try {
            localStorage.setItem(
                tabsKey(project.id),
                JSON.stringify({
                    open: tabs.map((tab) => tab.name),
                    active: activeTab?.name ?? null,
                }),
            );
        } catch {
            // The tabs are only remembered when the browser allows it.
        }
    }, [project.id, tabs, activeTab?.name]);

    const changeExplorer = (value: Explorer) => {
        setExplorer(value);

        try {
            localStorage.setItem(explorerKey, value);
        } catch {
            // Remembered only when the browser allows it.
        }
    };

    /**
     * Read the tables and their columns again, after a change of structure.
     */
    const reloadTables = () => {
        setSchemaVersion((version) => version + 1);
        router.reload({ only: ['tables', 'completion'] });
    };

    const openFile = (name: string, initial?: SqlFile) => {
        const existing = tabs.find((tab) => tab.name === name);

        if (existing) {
            setActiveId(existing.id);

            return;
        }

        const tab = newTab(name, initial);

        setOpen((state) => {
            const index = state.tabs.findIndex(
                (item) => item.id === state.activeId,
            );

            return {
                activeId: tab.id,
                tabs:
                    index < 0
                        ? [...state.tabs, tab]
                        : [
                              ...state.tabs.slice(0, index + 1),
                              tab,
                              ...state.tabs.slice(index + 1),
                          ],
            };
        });
    };

    const closeTabs = (ids: string[]) => {
        ids.forEach((id) => {
            views.current.delete(id);
            flushers.current.delete(id);
        });

        setOpen((state) => {
            let next = state.activeId;

            if (next && ids.includes(next)) {
                const index = state.tabs.findIndex((tab) => tab.id === next);

                next =
                    state.tabs
                        .slice(index + 1)
                        .find((tab) => !ids.includes(tab.id))?.id ??
                    [...state.tabs.slice(0, index)]
                        .reverse()
                        .find((tab) => !ids.includes(tab.id))?.id ??
                    null;
            }

            return {
                activeId: next,
                tabs: state.tabs.filter((tab) => !ids.includes(tab.id)),
            };
        });
    };

    /**
     * A new untitled file, empty or holding the given query, opened in a tab.
     */
    const createFile = async (sql = '') => {
        if (creating) {
            return;
        }

        setCreating(true);

        try {
            const file = await requestTableData<SqlFile>(
                'post',
                SqlFileController.store.url({ project: project.id }),
                { data: { sql } },
            );

            setFiles((current) =>
                [
                    ...current,
                    {
                        name: file.name,
                        size: new Blob([file.sql]).size,
                        modifiedAt: Date.now() / 1000,
                    },
                ].sort((a, b) =>
                    a.name.localeCompare(b.name, undefined, { numeric: true }),
                ),
            );
            openFile(file.name, file);
        } catch (exception) {
            toast.error("Le fichier n'a pas été créé", {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setCreating(false);
        }
    };

    const renameFile = async (name: string, newName: string) => {
        const tab = tabs.find((item) => item.name === name);

        try {
            // What was typed under the old name is written before the file moves.
            if (tab) {
                await flushers.current.get(tab.id)?.();
            }

            await requestTableData(
                'post',
                SqlFileController.rename.url({
                    project: project.id,
                    file: encodeURIComponent(name),
                }),
                { data: { name: newName } },
            );

            setFiles((current) =>
                current.map((file) =>
                    file.name === name ? { ...file, name: newName } : file,
                ),
            );
            setTabs((current) =>
                current.map((item) =>
                    item.name === name ? { ...item, name: newName } : item,
                ),
            );
        } catch (exception) {
            toast.error(`${name} n'a pas été renommé`, {
                description: (exception as TableRequestError).first(),
            });
        }
    };

    const deleteFile = async (name: string) => {
        const confirmed = await AlertDialog.call({
            title: `Supprimer ${name} ?`,
            description:
                'Le fichier est supprimé du dossier .opendb/sql du projet. Cela ne peut pas être annulé.',
            icon: Trash2Icon,
            variant: 'destructive',
            confirmText: 'Supprimer le fichier',
            cancelText: 'Annuler',
        });

        if (!confirmed) {
            return;
        }

        try {
            await requestTableData(
                'delete',
                SqlFileController.destroy.url({
                    project: project.id,
                    file: encodeURIComponent(name),
                }),
            );

            closeTabs(
                tabs.filter((tab) => tab.name === name).map((tab) => tab.id),
            );
            setFiles((current) => current.filter((file) => file.name !== name));
        } catch (exception) {
            toast.error(`${name} n'a pas été supprimé`, {
                description: (exception as TableRequestError).first(),
            });
        }
    };

    /**
     * The query a table opens with when no tab is shown: its latest rows, by id or creation date when it has one.
     */
    const tableQuery = (table: string) => {
        const columns = completion?.[table] ?? [];
        const order = ['id', 'created_at'].find((column) =>
            columns.includes(column),
        );

        return `SELECT * FROM ${plainIdentifier(table, driver)}${order ? ` ORDER BY ${order} DESC` : ''} LIMIT 100;`;
    };

    /**
     * Insert a name of the explorer at the cursor of the shown tab.
     */
    const insert = (text: string) => {
        const view = activeId ? views.current.get(activeId) : undefined;

        if (!view) {
            return;
        }

        view.dispatch(view.state.replaceSelection(text));
        view.focus();
    };

    /**
     * A script of the history, inserted on its own lines at the cursor of the shown tab.
     */
    const insertScript = (sql: string) => {
        const view = activeId ? views.current.get(activeId) : undefined;

        if (!view) {
            return;
        }

        const { from } = view.state.selection.main;
        const before =
            view.state.doc.lineAt(from).text.trim() === '' ? '' : '\n';

        insert(`${before}${sql}\n`);
    };

    const { defaultLayout, onLayoutChanged } = useDefaultLayout({
        id: 'project-sql-editor-layout',
        storage: localStorage,
        panelIds: ['sql-editor-explorer', 'sql-editor-workspace'],
    });

    return (
        <>
            <Head title={activeTab?.name ?? 'Éditeur SQL'} />

            <ResizablePanelGroup
                orientation="horizontal"
                defaultLayout={defaultLayout}
                onLayoutChanged={onLayoutChanged}
                className="h-full min-h-0 w-full gap-1"
            >
                <ResizablePanel
                    id="sql-editor-explorer"
                    defaultSize="272px"
                    minSize="200px"
                    maxSize="460px"
                    className="flex flex-col overflow-hidden rounded-lg border bg-sidebar"
                >
                    <Tabs
                        value={explorer}
                        onValueChange={(value) =>
                            changeExplorer(value as Explorer)
                        }
                        className="shrink-0 border-b px-2"
                    >
                        <TabsList variant="line">
                            <TabsTrigger value="files">Fichiers</TabsTrigger>
                            <TabsTrigger value="tables">Tables</TabsTrigger>
                            <TabsTrigger value="history">
                                Historique
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <div className="min-h-0 flex-1">
                        <Activity
                            mode={explorer === 'files' ? 'visible' : 'hidden'}
                        >
                            <FilesPanel
                                files={files}
                                current={activeTab?.name ?? null}
                                openFiles={tabs.map((tab) => tab.name)}
                                renaming={renaming}
                                onRenamingChange={setRenaming}
                                onOpen={openFile}
                                onCreate={() => void createFile()}
                                onRename={(name, newName) =>
                                    void renameFile(name, newName)
                                }
                                onDelete={(name) => void deleteFile(name)}
                                creating={creating}
                            />
                        </Activity>
                        <Activity
                            mode={explorer === 'tables' ? 'visible' : 'hidden'}
                        >
                            <TableList
                                projectId={project.id}
                                tables={tables}
                                current={null}
                                openTables={[]}
                                schemaVersion={schemaVersion}
                                columnsOnly
                                onOpen={(table) =>
                                    activeTab
                                        ? insert(plainIdentifier(table, driver))
                                        : void createFile(tableQuery(table))
                                }
                                onColumnSelect={(table, column) =>
                                    activeTab
                                        ? insert(
                                              plainIdentifier(column, driver),
                                          )
                                        : void createFile(
                                              `SELECT * FROM ${plainIdentifier(table, driver)} WHERE ${plainIdentifier(column, driver)} = '';`,
                                          )
                                }
                            />
                        </Activity>
                        <Activity
                            mode={explorer === 'history' ? 'visible' : 'hidden'}
                        >
                            <HistoryPanel
                                projectId={project.id}
                                active={explorer === 'history'}
                                version={historyVersion}
                                onInsert={(sql) =>
                                    activeTab
                                        ? insertScript(sql)
                                        : void createFile(sql)
                                }
                            />
                        </Activity>
                    </div>
                </ResizablePanel>
                <ResizableHandle
                    withHandle
                    className="cursor-col-resize bg-transparent"
                />
                <ResizablePanel
                    id="sql-editor-workspace"
                    className="flex min-w-0 flex-col"
                >
                    <TableTabs
                        label="Fichiers ouverts"
                        tabs={tabs.map((tab) => ({
                            id: tab.id,
                            kind: 'file' as const,
                            title: tab.name,
                            changes: null,
                            indicator: (
                                <SaveIndicator status={statuses[tab.id]} />
                            ),
                        }))}
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
                        onRename={(id) => {
                            const tab = tabs.find((item) => item.id === id);

                            if (tab) {
                                changeExplorer('files');
                                setRenaming(tab.name);
                            }
                        }}
                    />

                    {tabs.map((tab) => (
                        <Activity
                            key={tab.id}
                            mode={tab.id === activeId ? 'visible' : 'hidden'}
                        >
                            <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg rounded-t-none border bg-background">
                                <SqlFileTab
                                    projectId={project.id}
                                    name={tab.name}
                                    initial={tab.initial}
                                    driver={driver}
                                    tables={tableNames}
                                    completion={completion}
                                    onSchemaChanged={reloadTables}
                                    runUrl={runUrl}
                                    exportUrl={exportUrl}
                                    explainUrl={explainUrl}
                                    reviewUrl={reviewUrl}
                                    onRan={() =>
                                        setHistoryVersion(
                                            (version) => version + 1,
                                        )
                                    }
                                    onStatusChange={(status) =>
                                        setStatuses((current) => ({
                                            ...current,
                                            [tab.id]: status,
                                        }))
                                    }
                                    onEditorReady={(view) =>
                                        views.current.set(tab.id, view)
                                    }
                                    onFlushReady={(flush) =>
                                        flushers.current.set(tab.id, flush)
                                    }
                                    onSaved={(size) =>
                                        setFiles((current) =>
                                            current.map((file) =>
                                                file.name === tab.name
                                                    ? { ...file, size }
                                                    : file,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        </Activity>
                    ))}

                    {tabs.length === 0 && (
                        <div className="flex flex-1 items-center justify-center rounded-lg border bg-background p-6">
                            <EmptyState
                                icon={FileCodeIcon}
                                title={
                                    files.length
                                        ? 'Choisissez un fichier'
                                        : 'Aucun fichier SQL'
                                }
                                description="Chaque onglet est un fichier .sql du dossier .opendb/sql du projet, enregistré pendant que vous écrivez."
                                actions={[
                                    {
                                        label: 'Nouveau fichier',
                                        icon: FilePlusIcon,
                                        onClick: () => void createFile(),
                                        processing: creating,
                                    },
                                ]}
                            />
                        </div>
                    )}
                </ResizablePanel>
            </ResizablePanelGroup>
        </>
    );
}
