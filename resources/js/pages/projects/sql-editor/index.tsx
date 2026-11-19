import { snippet } from '@codemirror/autocomplete';
import { Head, router } from '@inertiajs/react';
import type { EditorView } from '@uiw/react-codemirror';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    FileCodeIcon,
    LockIcon,
    FilePlusIcon,
    LoaderCircleIcon,
    Trash2Icon,
} from 'lucide-react';
import { Activity, useEffect, useMemo, useRef, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { toast } from 'sonner';
import AlertDialog from '@/components/alert-dialog';
import { EmptyState } from '@/components/empty-state';
import {
    encodePath,
    filePaths,
    movedPath,
    parentPath,
} from '@/components/sql-editor/file-tree';
import FilesPanel from '@/components/sql-editor/files-panel';
import HistoryPanel from '@/components/sql-editor/history-panel';
import SnippetsPanel from '@/components/sql-editor/snippets-panel';
import SqlFileTab from '@/components/sql-editor/sql-file-tab';
import type { SaveStatus, SqlFile } from '@/components/sql-editor/types';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import {
    activeSnippets,
    plainTemplate,
} from '@/components/tables/console/sql-snippets';
import type {
    Snippet,
    SnippetConfig,
} from '@/components/tables/console/sql-snippets';
import TableList from '@/components/tables/table-list';
import TableTabs from '@/components/tables/table-tabs';
import type { TableSummary } from '@/components/tables/types';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { FileNode } from '@/hooks/use-file-tree';
import { usePrivileges } from '@/hooks/use-privileges';
import { useValueAsRef } from '@/hooks/use-value-as-ref';
import { trans, transChoice } from '@/lib/i18n';
import { registerProjectWork } from '@/lib/project-activity';
import { latestRowsQuery, plainIdentifier } from '@/lib/sql';
import { cn } from '@/lib/utils';
import type { Project } from '@/types/models/project';
import SnippetController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SnippetController';
import SqlEditorQueryController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlEditorQueryController';
import SqlFileController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlFileController';
import TableAssistantController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableAssistantController';

type Props = {
    project: Project;
    tables: TableSummary[];
    /**
     * The folders and the .sql files of .opendb/sql, as a tree whose ids are their paths.
     */
    files: FileNode[];
    /**
     * The columns of each table, deferred: the editor completes only the tables until they arrive.
     */
    completion?: Record<string, string[]>;
    /**
     * Only reads run in the editor of the project, and their results cannot be changed.
     */
    readOnly: boolean;
    /**
     * The snippets of the project, kept in its .opendb/snippets.json.
     */
    snippets: SnippetConfig;
};

/**
 * A tab keeps its id when its file is renamed or moved, so that the console keeps its results. Its name is the path
 * of the file in .opendb/sql.
 */
type OpenTab = {
    id: string;
    name: string;
    /**
     * The file as the editor just created it, not read again when its tab opens.
     */
    initial?: SqlFile;
};

type Explorer = 'files' | 'tables' | 'history' | 'snippets';

const EXPLORERS: Explorer[] = ['files', 'tables', 'history', 'snippets'];

/**
 * Without a remembered list, the first files are opened: a project with many scripts does not read them all.
 */
const OPENED_BY_DEFAULT = 5;

const tabsKey = (projectId: string) => `opendb:sql-editor-tabs:${projectId}`;

/**
 * The file another tab of the project asks to show, like the query of a table opened from the diagram.
 */
const OPEN_PARAMETER = 'open';
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
    files: string[],
): { tabs: OpenTab[]; active: string | null } {
    const names = new Set(files);

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

    const open = files.slice(0, OPENED_BY_DEFAULT);

    return { tabs: open.map((name) => newTab(name)), active: open[0] ?? null };
}

function SaveIndicator({ status }: { status: SaveStatus | undefined }) {
    const { t } = useLaravelReactI18n();

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
                        ? t('sql_editor.saving')
                        : t('sql_editor.unsaved_changes')
                }
            />
        );
    }

    if (status === 'conflict' || status === 'error') {
        return (
            <span
                className="size-1.5 shrink-0 rounded-full bg-destructive"
                aria-label={t('sql_editor.not_saved')}
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
    readOnly: initialReadOnly,
    snippets: initialSnippets,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [snippetConfig, setSnippetConfig] = useState(initialSnippets);
    const [readOnly, setReadOnly] = useState(initialReadOnly);
    const [switchingReadOnly, setSwitchingReadOnly] = useState(false);
    // Only who manages the project may lock it or unlock it.
    const canManageProject = usePrivileges().canOnProject('manage_project');
    const [files, setFiles] = useState(initialFiles);
    const paths = useMemo(() => filePaths(files), [files]);
    const [{ tabs, activeId }, setOpen] = useState(() => {
        const initialPaths = filePaths(initialFiles);
        const restored = restoreTabs(project.id, initialPaths);
        const requested = new URLSearchParams(window.location.search).get(
            OPEN_PARAMETER,
        );

        if (requested && initialPaths.includes(requested)) {
            const tab =
                restored.tabs.find((item) => item.name === requested) ??
                newTab(requested);

            return {
                tabs: restored.tabs.includes(tab)
                    ? restored.tabs
                    : [...restored.tabs, tab],
                activeId: tab.id,
            };
        }

        return {
            tabs: restored.tabs,
            activeId:
                restored.tabs.find((tab) => tab.name === restored.active)?.id ??
                null,
        };
    });

    // The requested file is opened once: reloading the page gives back the remembered tabs.
    useEffect(() => {
        const url = new URL(window.location.href);

        if (url.searchParams.has(OPEN_PARAMETER)) {
            url.searchParams.delete(OPEN_PARAMETER);
            window.history.replaceState(window.history.state, '', url);
        }
    }, []);
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

    // Leaving the project writes what was typed, but loses a file the user has not settled after a conflict or an error.
    const tabsRef = useValueAsRef(tabs);
    const statusesRef = useValueAsRef(statuses);

    useEffect(
        () =>
            registerProjectWork({
                describe: () => ({
                    unsavedFiles: tabsRef.current
                        .filter((tab) =>
                            ['conflict', 'error'].includes(
                                statusesRef.current[tab.id] ?? '',
                            ),
                        )
                        .map((tab) => tab.name),
                }),
                settle: async () => {
                    await Promise.allSettled(
                        [...flushers.current.values()].map((flush) => flush()),
                    );
                },
            }),
        [statusesRef, tabsRef],
    );

    const driver =
        project.driver_config?.driver_id ??
        project.driver_config?.driver?.id ??
        '';
    const tableNames = tables.map((item) => item.name);
    const args = { project: project.id };
    const editor = {
        runUrl: SqlEditorQueryController.run.url(args),
        rowsUrl: SqlEditorQueryController.rows.url(args),
        cancelUrl: SqlEditorQueryController.cancel.url(args),
        exportUrl: SqlEditorQueryController.exportMethod.url(args),
        explainUrl: SqlEditorQueryController.explain.url(args),
        reviewUrl: TableAssistantController.reviewQuery.url(args),
        // SQLite runs in the server itself: a query cannot be interrupted from another request.
        canCancel: driver !== 'sqlite',
        readOnly,
        onRan: () => setHistoryVersion((version) => version + 1),
    };

    const changeReadOnly = async (next: boolean) => {
        setSwitchingReadOnly(true);

        try {
            await requestTableData(
                'put',
                SqlEditorQueryController.readOnly.url(args),
                { data: { readOnly: next } },
            );
            setReadOnly(next);
        } catch (exception) {
            toast.error(trans('sql_editor.read_only_failed'), {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setSwitchingReadOnly(false);
        }
    };
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
     * The tree of the files read again after a change: the server sorts it as the disk holds it.
     */
    const reloadFiles = async () => {
        try {
            setFiles(
                await requestTableData<FileNode[]>(
                    'get',
                    SqlFileController.index.url(args),
                ),
            );
        } catch {
            // The tree shown stays as it was: the next change lists it again.
        }
    };

    /**
     * A new untitled file in the given folder, empty or holding the given query, opened in a tab.
     */
    const createFile = async (sql = '', folder: string | null = null) => {
        if (creating) {
            return;
        }

        setCreating(true);

        try {
            const file = await requestTableData<SqlFile>(
                'post',
                SqlFileController.store.url(args),
                { data: { sql, folder } },
            );

            openFile(file.name, file);
            void reloadFiles();
        } catch (exception) {
            toast.error(trans('sql_editor.create_failed'), {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setCreating(false);
        }
    };

    /**
     * A new folder in the given folder, whose name is edited at once.
     */
    const createFolder = async (parent: string | null) => {
        try {
            const folder = await requestTableData<{ name: string }>(
                'post',
                SqlFileController.storeFolder.url(args),
                { data: { parent } },
            );

            await reloadFiles();
            changeExplorer('files');
            setRenaming(folder.name);
        } catch (exception) {
            toast.error(trans('sql_editor.create_folder_failed'), {
                description: (exception as TableRequestError).first(),
            });
        }
    };

    /**
     * Rename a file or a folder, or move it to another folder: the tabs of the files it holds follow it.
     */
    const moveEntry = async (node: FileNode, newPath: string) => {
        const moved = tabs.filter((tab) =>
            node.type === 'file'
                ? tab.name === node.id
                : movedPath(tab.name, node.id, newPath) !== tab.name,
        );

        try {
            // What was typed under the old path is written before the file moves.
            await Promise.all(
                moved.map((tab) => flushers.current.get(tab.id)?.()),
            );

            await requestTableData(
                'post',
                node.type === 'file'
                    ? SqlFileController.rename.url({
                          project: project.id,
                          file: encodePath(node.id),
                      })
                    : SqlFileController.renameFolder.url({
                          project: project.id,
                          folder: encodePath(node.id),
                      }),
                { data: { name: newPath } },
            );

            setTabs((current) =>
                current.map((tab) => ({
                    ...tab,
                    name: movedPath(tab.name, node.id, newPath),
                })),
            );
        } catch (exception) {
            toast.error(
                trans(
                    parentPath(node.id) === parentPath(newPath)
                        ? 'sql_editor.rename_failed'
                        : 'sql_editor.move_failed',
                    { name: node.name },
                ),
                { description: (exception as TableRequestError).first() },
            );
        } finally {
            void reloadFiles();
        }
    };

    /**
     * Delete a file, or a folder with everything it holds, once the user agrees to it: their tabs close.
     */
    const deleteEntry = async (node: FileNode) => {
        const isFolder = node.type === 'folder';
        const confirmed = await AlertDialog.call({
            title: trans(
                isFolder
                    ? 'sql_editor.delete_folder_title'
                    : 'sql_editor.delete_title',
                { name: node.name },
            ),
            description: isFolder
                ? transChoice(
                      'sql_editor.delete_folder_description',
                      filePaths(node.children ?? []).length,
                  )
                : trans('sql_editor.delete_description'),
            icon: Trash2Icon,
            variant: 'destructive',
            confirmText: trans(
                isFolder
                    ? 'sql_editor.delete_folder_confirm'
                    : 'sql_editor.delete_confirm',
            ),
            cancelText: trans('common.cancel'),
        });

        if (!confirmed) {
            return;
        }

        try {
            await requestTableData(
                'delete',
                isFolder
                    ? SqlFileController.destroyFolder.url({
                          project: project.id,
                          folder: encodePath(node.id),
                      })
                    : SqlFileController.destroy.url({
                          project: project.id,
                          file: encodePath(node.id),
                      }),
            );

            closeTabs(
                tabs
                    .filter((tab) =>
                        isFolder
                            ? tab.name.startsWith(`${node.id}/`)
                            : tab.name === node.id,
                    )
                    .map((tab) => tab.id),
            );
            void reloadFiles();
        } catch (exception) {
            toast.error(
                trans('sql_editor.delete_failed', { name: node.name }),
                {
                    description: (exception as TableRequestError).first(),
                },
            );
        }
    };

    /**
     * The query a table opens with when no tab is shown.
     */
    const tableQuery = (table: string) =>
        latestRowsQuery(table, completion?.[table] ?? [], driver);

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
    const snippets = useMemo(
        () => activeSnippets(driver, snippetConfig),
        [driver, snippetConfig],
    );

    /**
     * Save every snippet of the project. Resolves with the message of the error, or null once saved.
     */
    const saveSnippets = async (
        config: SnippetConfig,
    ): Promise<string | null> => {
        try {
            setSnippetConfig(
                await requestTableData<SnippetConfig>(
                    'put',
                    SnippetController.update.url(args),
                    {
                        data: {
                            snippets: config.snippets,
                            hidden: config.hidden,
                        },
                    },
                ),
            );

            return null;
        } catch (exception) {
            return (exception as TableRequestError).first();
        }
    };

    /**
     * A snippet inserted at the cursor of the shown tab, its fields ready to be filled in with Tab, or the first
     * content of a new file when no tab is open.
     */
    const insertSnippet = (item: Snippet) => {
        const view = activeId ? views.current.get(activeId) : undefined;

        if (!view) {
            void createFile(plainTemplate(item.template));

            return;
        }

        const { from, to } = view.state.selection.main;

        snippet(item.template)(view, null, from, to);
        view.focus();
    };

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
            <Head title={activeTab?.name ?? t('projects.menu.sql_editor')} />

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
                    <ScrollArea className="w-full shrink-0 border-b [&>[data-orientation=vertical]]:hidden">
                        <Tabs
                            value={explorer}
                            onValueChange={(value) =>
                                changeExplorer(value as Explorer)
                            }
                            className="w-max min-w-full px-2 pb-1"
                        >
                            <TabsList variant="line">
                                <TabsTrigger value="files">
                                    {t('sql_editor.explorer.files')}
                                </TabsTrigger>
                                <TabsTrigger value="tables">
                                    {t('sql_editor.explorer.tables')}
                                </TabsTrigger>
                                <TabsTrigger value="history">
                                    {t('sql_editor.explorer.history')}
                                </TabsTrigger>
                                <TabsTrigger value="snippets">
                                    {t('sql_editor.explorer.snippets')}
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                        <ScrollBar
                            orientation="horizontal"
                            className="h-2 border-t-0"
                        />
                    </ScrollArea>
                    <div className="min-h-0 flex-1">
                        <Activity
                            mode={explorer === 'files' ? 'visible' : 'hidden'}
                        >
                            <FilesPanel
                                projectId={project.id}
                                files={files}
                                current={activeTab?.name ?? null}
                                openFiles={tabs.map((tab) => tab.name)}
                                renaming={renaming}
                                onRenamingChange={setRenaming}
                                onOpen={openFile}
                                onCreateFile={(folder) =>
                                    void createFile('', folder)
                                }
                                onCreateFolder={(parent) =>
                                    void createFolder(parent)
                                }
                                onMove={(node, newPath) =>
                                    void moveEntry(node, newPath)
                                }
                                onDelete={(node) => void deleteEntry(node)}
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
                        <Activity
                            mode={
                                explorer === 'snippets' ? 'visible' : 'hidden'
                            }
                        >
                            <SnippetsPanel
                                driver={driver}
                                config={snippetConfig}
                                onSave={saveSnippets}
                                onInsert={insertSnippet}
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
                    <div className="flex min-w-0 items-end gap-3">
                        <div className="min-w-0 flex-1">
                            <TableTabs
                                label={t('sql_editor.open_files')}
                                tabs={tabs.map((tab) => ({
                                    id: tab.id,
                                    kind: 'file' as const,
                                    title: tab.name,
                                    changes: null,
                                    indicator: (
                                        <SaveIndicator
                                            status={statuses[tab.id]}
                                        />
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
                                onCloseAll={() =>
                                    closeTabs(tabs.map((tab) => tab.id))
                                }
                                onRename={(id) => {
                                    const tab = tabs.find(
                                        (item) => item.id === id,
                                    );

                                    if (tab) {
                                        changeExplorer('files');
                                        setRenaming(tab.name);
                                    }
                                }}
                            />
                        </div>
                        <label
                            className="mb-1.5 flex shrink-0 items-center gap-2 pr-3 text-xs text-muted-foreground"
                            title={t('sql_editor.read_only_description')}
                        >
                            <LockIcon className="size-3.5" />
                            {t('ai.access_modes.read-only')}
                            <Switch
                                size="sm"
                                checked={readOnly}
                                disabled={switchingReadOnly || !canManageProject}
                                onCheckedChange={(checked) =>
                                    void changeReadOnly(checked)
                                }
                            />
                        </label>
                    </div>

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
                                    snippets={snippets}
                                    onSchemaChanged={reloadTables}
                                    editor={editor}
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
                                />
                            </div>
                        </Activity>
                    ))}

                    {tabs.length === 0 && (
                        <div className="flex flex-1 items-center justify-center rounded-lg border bg-background p-6">
                            <EmptyState
                                icon={FileCodeIcon}
                                title={
                                    paths.length
                                        ? t('sql_editor.choose_file')
                                        : t('sql_editor.no_file')
                                }
                                description={t(
                                    'sql_editor.no_file_description',
                                )}
                                actions={[
                                    {
                                        label: t('sql_editor.new_file'),
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
