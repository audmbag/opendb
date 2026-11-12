import type { ItemInstance } from '@headless-tree/core';
import {
    dragAndDropFeature,
    hotkeysCoreFeature,
    syncDataLoaderFeature,
} from '@headless-tree/core';
import { useTree } from '@headless-tree/react';
import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ChevronsDownUpIcon,
    FileCodeIcon,
    FilePlusIcon,
    FolderPlusIcon,
    PencilIcon,
    SearchIcon,
    Trash2Icon,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import {
    Fragment,
    useDeferredValue,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import { Tree, TreeItem, TreeItemLabel } from '@/components/reui/tree';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    Editable,
    EditableArea,
    EditableInput,
    EditablePreview,
} from '@/components/ui/editable';
import type { FileNode } from '@/hooks/use-file-tree';
import {
    buildIndex,
    getFileIcon,
    isSameOrParentPath,
} from '@/hooks/use-file-tree';
import { cn } from '@/lib/utils';
import {
    ancestors,
    filePaths,
    filterTree,
    folderIds,
    joinPath,
    parentPath,
    ROOT_ID,
} from './file-tree';

const INDENT = 14;

type Props = {
    projectId: string;
    /**
     * The folders and the files of .opendb/sql, as a tree whose ids are their paths.
     */
    files: FileNode[];
    /**
     * The file of the tab shown, and the files opened in a tab.
     */
    current: string | null;
    openFiles: string[];
    /**
     * The file or the folder whose name is being edited, from the panel or the menu of a tab.
     */
    renaming: string | null;
    onRenamingChange: (path: string | null) => void;
    onOpen: (path: string) => void;
    /**
     * A new file or folder in the given folder, null at the root.
     */
    onCreateFile: (folder: string | null) => void;
    onCreateFolder: (parent: string | null) => void;
    /**
     * A file or a folder renamed, or dropped on another folder.
     */
    onMove: (node: FileNode, newPath: string) => void;
    onDelete: (node: FileNode) => void;
    creating: boolean;
};

const expandedKey = (projectId: string) =>
    `opendb:sql-editor-folders:${projectId}`;

/**
 * The folders opened the last time, kept by the browser.
 */
function restoreExpanded(projectId: string): string[] {
    try {
        const stored = JSON.parse(
            localStorage.getItem(expandedKey(projectId)) ?? '[]',
        ) as unknown;

        return Array.isArray(stored)
            ? stored.filter((id): id is string => typeof id === 'string')
            : [];
    } catch {
        return [];
    }
}

/**
 * The folders and the .sql files of the project, as an explorer: each file opens in a tab, which saves it as the
 * user types; a file or a folder is moved by dropping it on another folder, or on the empty space for the root.
 */
export default function FilesPanel({
    projectId,
    files,
    current,
    openFiles,
    renaming,
    onRenamingChange,
    onOpen,
    onCreateFile,
    onCreateFolder,
    onMove,
    onDelete,
    creating,
}: Props) {
    'use no memo';

    const { t } = useLaravelReactI18n();
    const userId = usePage().props.auth.user.id;
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState(() => restoreExpanded(projectId));
    /**
     * The folder the buttons of the header create in: the last folder clicked, or the folder of the last file.
     */
    const [lastFolder, setLastFolder] = useState<string | null>(null);
    const [menuNode, setMenuNode] = useState<FileNode | null>(null);
    const renameAfterClose = useRef<string | null>(null);
    const deferredSearch = useDeferredValue(search.trim().toLowerCase());
    const searching = deferredSearch !== '';

    const index = useMemo(() => buildIndex(files), [files]);
    const fileCount = useMemo(() => filePaths(files).length, [files]);
    const visible = useMemo(
        () => (searching ? filterTree(files, deferredSearch) : files),
        [files, deferredSearch, searching],
    );
    const visibleIndex = useMemo(
        () => (searching ? buildIndex(visible) : index),
        [searching, visible, index],
    );
    // Every folder holding a match is open while searching.
    const searchExpanded = useMemo(() => folderIds(visible), [visible]);
    const root = useMemo<FileNode>(
        () => ({ id: ROOT_ID, name: '', type: 'folder', children: visible }),
        [visible],
    );
    const targetFolder =
        lastFolder !== null && index.has(lastFolder) ? lastFolder : null;

    useEffect(() => {
        try {
            localStorage.setItem(
                expandedKey(projectId),
                JSON.stringify(expanded),
            );
        } catch {
            // Remembered only when the browser allows it.
        }
    }, [projectId, expanded]);

    // The file shown and the entry being renamed open the folders leading to them, which can be closed again.
    const revealing = [current, renaming].filter(
        (path): path is string => path !== null,
    );
    const [revealed, setRevealed] = useState<string | null>(null);

    if (revealed !== revealing.join('\n')) {
        setRevealed(revealing.join('\n'));

        const missing = revealing
            .flatMap(ancestors)
            .filter((id) => !expanded.includes(id));

        if (missing.length > 0) {
            setExpanded([...expanded, ...new Set(missing)]);
        }
    }

    const destinationOf = (target: ItemInstance<FileNode>): string | null =>
        target.getId() === ROOT_ID ? null : target.getId();

    /**
     * A file or a folder moves to another folder, never into itself or where it already is.
     */
    const canMoveInto = (
        items: ItemInstance<FileNode>[],
        destination: string | null,
    ): boolean =>
        items.every((item) => {
            const path = item.getId();

            return (
                parentPath(path) !== destination &&
                !(
                    item.isFolder() &&
                    destination !== null &&
                    isSameOrParentPath(path, destination)
                )
            );
        });

    const tree = useTree<FileNode>({
        rootItemId: ROOT_ID,
        indent: INDENT,
        getItemName: (item) => item.getItemData().name,
        isItemFolder: (item) => item.getItemData()?.type === 'folder',
        dataLoader: {
            getItem: (id) =>
                (id === ROOT_ID ? root : visibleIndex.get(id)) ?? {
                    id,
                    name: id,
                    type: 'file',
                },
            getChildren: (id) =>
                (
                    (id === ROOT_ID ? root : visibleIndex.get(id))?.children ??
                    []
                ).map((child) => child.id),
        },
        state: { expandedItems: searching ? searchExpanded : expanded },
        setExpandedItems: (updater) => {
            if (!searching) {
                setExpanded((previous) =>
                    typeof updater === 'function' ? updater(previous) : updater,
                );
            }
        },
        onPrimaryAction: (item) => {
            const node = item.getItemData();

            if (node.type === 'folder') {
                setLastFolder(node.id);
            } else {
                setLastFolder(parentPath(node.id));
                onOpen(node.id);
            }
        },
        canReorder: false,
        canDrag: () => renaming === null,
        canDrop: (items, target) =>
            canMoveInto(items, destinationOf(target.item)),
        onDrop: (items, target) => {
            const destination = destinationOf(target.item);

            if (!canMoveInto(items, destination)) {
                return;
            }

            items.forEach((item) =>
                onMove(
                    item.getItemData(),
                    joinPath(destination, item.getItemData().name),
                ),
            );

            if (destination !== null) {
                setExpanded((previous) =>
                    previous.includes(destination)
                        ? previous
                        : [...previous, destination],
                );
            }
        },
        hotkeys: {
            customRename: {
                hotkey: 'F2',
                preventDefault: true,
                handler: (_, instance) =>
                    onRenamingChange(instance.getFocusedItem().getId()),
            },
            customDelete: {
                hotkey: 'Delete',
                preventDefault: true,
                handler: (_, instance) =>
                    onDelete(instance.getFocusedItem().getItemData()),
            },
        },
        features: [
            syncDataLoaderFeature,
            hotkeysCoreFeature,
            dragAndDropFeature,
        ],
    });

    // The tree follows the files listed again after a change, and the search.
    useEffect(() => {
        tree.rebuildTree();
    }, [tree, visible]);

    const items = tree.getItems();
    const firstMatch = searching ? filePaths(visible)[0] : undefined;
    const dropOnRoot = tree.getDragTarget()?.item.getId() === ROOT_ID;

    const submitRename = (node: FileNode, value: string) => {
        onRenamingChange(null);

        const trimmed = value.trim();
        const name =
            node.type === 'file' && !trimmed.endsWith('.sql')
                ? `${trimmed}.sql`
                : trimmed;

        if (trimmed !== '' && name !== node.name) {
            onMove(node, joinPath(parentPath(node.id), name));
        }
    };

    const startRenameAfterClose = (path: string) => {
        renameAfterClose.current = path;
    };

    // The files of the user live in their own folder of the project.
    const folderLabel = (folder: string | null) =>
        folder === null ? `.opendb/users/${userId}/sql` : folder;

    return (
        <nav
            aria-label={t('sql_editor.files.title')}
            className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden"
        >
            <div className="flex min-w-0 items-center gap-0.5 px-2 pt-2 pb-1.5">
                <h2 className="flex min-w-0 flex-1 items-baseline gap-2 truncate pl-1 text-sm font-medium">
                    {t('sql_editor.explorer.files')}
                    <span className="text-xs font-normal text-muted-foreground tabular-nums">
                        {fileCount}
                    </span>
                </h2>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => onCreateFile(targetFolder)}
                    processing={creating}
                    aria-label={t('sql_editor.files.new_in', {
                        folder: folderLabel(targetFolder),
                    })}
                    tooltip={t('sql_editor.files.new_in', {
                        folder: folderLabel(targetFolder),
                    })}
                >
                    <FilePlusIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => onCreateFolder(targetFolder)}
                    aria-label={t('sql_editor.files.new_folder_in', {
                        folder: folderLabel(targetFolder),
                    })}
                    tooltip={t('sql_editor.files.new_folder_in', {
                        folder: folderLabel(targetFolder),
                    })}
                >
                    <FolderPlusIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => setExpanded([])}
                    disabled={expanded.length === 0 || searching}
                    aria-label={t('sql_editor.files.collapse_all')}
                    tooltip={t('sql_editor.files.collapse_all')}
                >
                    <ChevronsDownUpIcon />
                </Button>
            </div>

            {fileCount > 0 && (
                <label className="relative mx-2 mb-2 flex items-center">
                    <span className="sr-only">
                        {t('sql_editor.files.search')}
                    </span>
                    <SearchIcon className="pointer-events-none absolute left-2.5 size-4 text-muted-foreground" />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && firstMatch) {
                                onOpen(firstMatch);
                            } else if (event.key === 'Escape') {
                                setSearch('');
                            }
                        }}
                        placeholder={t('common.search')}
                        className="h-8 w-full rounded-md border border-input bg-transparent pr-2 pl-8 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                </label>
            )}

            <ContextMenu modal={false}>
                <ContextMenuTrigger asChild>
                    <div
                        className="flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto px-2 pb-2"
                        onContextMenu={(event) => {
                            const path = (
                                event.target as HTMLElement
                            ).closest<HTMLElement>('[data-path]')?.dataset.path;

                            setMenuNode(
                                path ? (index.get(path) ?? null) : null,
                            );
                        }}
                        onClick={(event) => {
                            // A click beside the entries creates at the root again.
                            if (
                                !(event.target as HTMLElement).closest(
                                    '[data-path]',
                                )
                            ) {
                                setLastFolder(null);
                            }
                        }}
                    >
                        {files.length === 0 ? (
                            <EmptyState
                                icon={FileCodeIcon}
                                title={t('sql_editor.files.empty')}
                                description={t(
                                    'sql_editor.files.empty_description',
                                )}
                                actions={[
                                    {
                                        label: t('sql_editor.new_file'),
                                        icon: FilePlusIcon,
                                        variant: 'outline',
                                        onClick: () => onCreateFile(null),
                                    },
                                ]}
                                className="py-6"
                            />
                        ) : searching && visible.length === 0 ? (
                            <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                                {t('sql_editor.files.no_match')}
                            </p>
                        ) : (
                            <Tree
                                indent={INDENT}
                                tree={tree}
                                className={cn(
                                    'min-h-full flex-1 rounded-md transition-colors',
                                    dropOnRoot && 'bg-muted/60',
                                )}
                            >
                                {items.map((item) => {
                                    const node = item.getItemData();
                                    const level = item.getItemMeta().level;
                                    const selected = node.id === current;
                                    const opened = openFiles.includes(node.id);
                                    const isEmptyFolder =
                                        item.isFolder() &&
                                        item.isExpanded() &&
                                        item.getChildren().length === 0;

                                    if (renaming === node.id) {
                                        return (
                                            <div
                                                key={node.id}
                                                className="pb-0.5"
                                                style={{
                                                    paddingInlineStart:
                                                        level * INDENT,
                                                }}
                                            >
                                                <Editable
                                                    defaultValue={node.name}
                                                    editing
                                                    onSubmit={(value) =>
                                                        submitRename(
                                                            node,
                                                            value,
                                                        )
                                                    }
                                                    onCancel={() =>
                                                        onRenamingChange(null)
                                                    }
                                                    placeholder={
                                                        node.type === 'file'
                                                            ? 'nom.sql'
                                                            : t(
                                                                  'sql_editor.files.new_folder',
                                                              )
                                                    }
                                                    className="h-8 px-1 text-sm"
                                                >
                                                    <EditableArea>
                                                        <EditablePreview />
                                                        <EditableInput
                                                            className="px-1"
                                                            autoComplete="off"
                                                            aria-label={t(
                                                                'sql_editor.files.new_name',
                                                                {
                                                                    file: node.name,
                                                                },
                                                            )}
                                                        />
                                                    </EditableArea>
                                                </Editable>
                                            </div>
                                        );
                                    }

                                    return (
                                        <Fragment key={node.id}>
                                            <TreeItem
                                                item={item}
                                                data-path={node.id}
                                                data-selected={selected}
                                                aria-current={
                                                    selected
                                                        ? 'page'
                                                        : undefined
                                                }
                                                title={node.id}
                                                onDoubleClick={() =>
                                                    node.type === 'file' &&
                                                    onRenamingChange(node.id)
                                                }
                                                className="w-full text-left"
                                            >
                                                <TreeItemLabel
                                                    className={cn(
                                                        'h-8 min-w-0 bg-transparent py-0 hover:bg-muted in-data-[drag-target=true]:bg-muted in-data-[selected=true]:bg-muted in-data-[selected=true]:font-medium in-data-[selected=true]:text-foreground',
                                                        !selected &&
                                                            'text-muted-foreground hover:text-foreground',
                                                    )}
                                                >
                                                    <span className="flex min-w-0 flex-1 items-center gap-2">
                                                        {getFileIcon(
                                                            node,
                                                            item.isExpanded(),
                                                        )}
                                                        <span className="min-w-0 flex-1 truncate">
                                                            {node.name}
                                                        </span>
                                                    </span>
                                                    {opened && !selected && (
                                                        <span
                                                            className="size-1.5 shrink-0 rounded-full bg-muted-foreground/60"
                                                            aria-label={t(
                                                                'sql_editor.files.open_in_tab',
                                                            )}
                                                        />
                                                    )}
                                                </TreeItemLabel>
                                            </TreeItem>
                                            {isEmptyFolder && (
                                                <div
                                                    className="py-1 ps-(--tree-padding) text-xs text-muted-foreground italic"
                                                    style={
                                                        {
                                                            '--tree-padding': `${(level + 1) * INDENT + 36}px`,
                                                        } as CSSProperties
                                                    }
                                                >
                                                    {t(
                                                        'sql_editor.files.empty_folder',
                                                    )}
                                                </div>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </Tree>
                        )}
                    </div>
                </ContextMenuTrigger>
                <ContextMenuContent
                    className="w-52"
                    onCloseAutoFocus={(event) => {
                        // The field replaces the entry: the focus must stay in it.
                        if (renameAfterClose.current) {
                            event.preventDefault();
                            onRenamingChange(renameAfterClose.current);
                            renameAfterClose.current = null;
                        }
                    }}
                >
                    {menuNode?.type === 'file' && (
                        <ContextMenuItem onSelect={() => onOpen(menuNode.id)}>
                            <FileCodeIcon />
                            {t('tables.list.open')}
                        </ContextMenuItem>
                    )}
                    {menuNode?.type !== 'file' && (
                        <>
                            <ContextMenuItem
                                onSelect={() =>
                                    onCreateFile(menuNode?.id ?? null)
                                }
                            >
                                <FilePlusIcon />
                                {t('sql_editor.new_file')}
                            </ContextMenuItem>
                            <ContextMenuItem
                                onSelect={() =>
                                    onCreateFolder(menuNode?.id ?? null)
                                }
                            >
                                <FolderPlusIcon />
                                {t('sql_editor.files.new_folder')}
                            </ContextMenuItem>
                        </>
                    )}
                    {menuNode && (
                        <>
                            <ContextMenuSeparator />
                            <ContextMenuItem
                                onSelect={() =>
                                    startRenameAfterClose(menuNode.id)
                                }
                            >
                                <PencilIcon />
                                {t('tables.workspace.rename')}
                            </ContextMenuItem>
                            <ContextMenuSeparator />
                            <ContextMenuItem
                                variant="destructive"
                                onSelect={() => onDelete(menuNode)}
                            >
                                <Trash2Icon />
                                {t('common.delete')}
                            </ContextMenuItem>
                        </>
                    )}
                </ContextMenuContent>
            </ContextMenu>
        </nav>
    );
}
