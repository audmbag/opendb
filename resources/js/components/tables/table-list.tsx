import { useVirtualizer } from '@tanstack/react-virtual';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BotOffIcon,
    ChevronRightIcon,
    CopyIcon,
    CopyPlusIcon,
    DownloadIcon,
    EllipsisVerticalIcon,
    EraserIcon,
    FileUpIcon,
    KeyRoundIcon,
    LinkIcon,
    ListTreeIcon,
    PencilRulerIcon,
    PlusIcon,
    SearchIcon,
    SquareTerminalIcon,
    Table2Icon,
    TextCursorInputIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import { useDeferredValue, useEffect, useRef, useState } from 'react';
import { useHotkeys } from 'react-hotkeys-hook';
import Button from '@/components/form/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Kbd } from '@/components/ui/kbd';
import { usePrivileges } from '@/hooks/use-privileges';
import { cn } from '@/lib/utils';
import type { Privilege } from '@/types/models/privilege';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';
import type { TableRequestError } from './api';
import { requestTableData } from './api';
import type { TableOutline, TableSummary } from './types';

export type TableAction =
    | 'open'
    | 'design'
    | 'rename'
    | 'duplicate'
    | 'import'
    | 'export'
    | 'truncate'
    | 'drop';

type Props = {
    projectId: string;
    tables: TableSummary[];
    current: string | null;
    /**
     * The tables opened in a tab, marked in the list.
     */
    openTables: string[];
    /**
     * Changes whenever the structure of the database changes, so that the unfolded tables are read again.
     */
    schemaVersion: number;
    onOpen: (table: string) => void;
    /**
     * The actions on the tables, in their context menu and in the header. The SQL editor, which only reads the
     * tree, leaves them out.
     */
    onAction?: (action: TableAction, table: string) => void;
    onCreateTable?: () => void;
    onOpenConsole?: () => void;
    onImport?: () => void;
    onExport?: () => void;
    /**
     * A click on a column, like the SQL editor inserting its name at the cursor. Without it, the columns are not buttons.
     */
    onColumnSelect?: (table: string, column: string) => void;
    /**
     * An unfolded table lists only its columns, without its keys and indexes, as the SQL editor needs.
     */
    columnsOnly?: boolean;
};

type Section = 'columns' | 'keys' | 'indexes';

type Outline =
    | { status: 'loading' }
    | { status: 'error'; message: string }
    | { status: 'loaded'; outline: TableOutline };

type Node =
    | { kind: 'table'; key: string; table: TableSummary; expanded: boolean }
    | { kind: 'loading'; key: string }
    | { kind: 'error'; key: string; message: string }
    | {
          kind: 'section';
          key: string;
          table: string;
          section: Section;
          count: number;
          open: boolean;
      }
    | {
          kind: 'column';
          key: string;
          table: string;
          name: string;
          type: string;
          nullable: boolean;
          primary: boolean;
          foreign: boolean;
          defaultValue: string | null;
      }
    | {
          kind: 'key';
          key: string;
          label: string;
          columns: string[];
          target: { table: string; columns: string[] } | null;
      }
    | {
          kind: 'index';
          key: string;
          name: string;
          columns: string[];
          unique: boolean;
      }
    | { kind: 'empty'; key: string; text: string };

const TABLE_HEIGHT = 32;
const DETAIL_HEIGHT = 26;

const SECTION_LABELS: Record<Section, string> = {
    columns: 'tables.list.sections.columns',
    keys: 'tables.list.sections.keys',
    indexes: 'tables.list.sections.indexes',
};

/**
 * The children of an unfolded table: its sections, and the content of the open ones.
 */
function outlineNodes(
    table: string,
    outline: TableOutline,
    openSections: Set<string>,
    columnsOnly: boolean,
): Node[] {
    const nodes: Node[] = [];
    const foreignColumns = new Set(
        outline.foreign_keys.flatMap((foreignKey) => foreignKey.columns),
    );
    const keyCount =
        (outline.primary_key.length > 0 ? 1 : 0) + outline.foreign_keys.length;
    const counts: Record<Section, number> = {
        columns: outline.columns.length,
        keys: keyCount,
        indexes: outline.indexes.length,
    };

    const sections: Section[] = columnsOnly
        ? ['columns']
        : ['columns', 'keys', 'indexes'];

    for (const section of sections) {
        const sectionKey = `${table}:${section}`;
        // Alone, the columns are listed right under their table, without a section to unfold.
        const open = columnsOnly || openSections.has(sectionKey);

        if (!columnsOnly) {
            nodes.push({
                kind: 'section',
                key: sectionKey,
                table,
                section,
                count: counts[section],
                open,
            });
        }

        if (!open) {
            continue;
        }

        if (counts[section] === 0) {
            nodes.push({
                kind: 'empty',
                key: `${sectionKey}:empty`,
                text:
                    section === 'keys'
                        ? 'tables.list.no_key'
                        : section === 'indexes'
                          ? 'tables.list.no_index'
                          : 'tables.list.no_column',
            });

            continue;
        }

        if (section === 'columns') {
            for (const column of outline.columns) {
                nodes.push({
                    kind: 'column',
                    key: `${sectionKey}:${column.name}`,
                    table,
                    name: column.name,
                    type: column.type,
                    nullable: column.nullable,
                    primary: outline.primary_key.includes(column.name),
                    foreign: foreignColumns.has(column.name),
                    defaultValue: column.default,
                });
            }
        }

        if (section === 'keys') {
            if (outline.primary_key.length > 0) {
                nodes.push({
                    kind: 'key',
                    key: `${sectionKey}:primary`,
                    label: 'Primaire',
                    columns: outline.primary_key,
                    target: null,
                });
            }

            for (const foreignKey of outline.foreign_keys) {
                nodes.push({
                    kind: 'key',
                    key: `${sectionKey}:${foreignKey.name}:${foreignKey.columns.join()}`,
                    label: foreignKey.name,
                    columns: foreignKey.columns,
                    target: {
                        table: foreignKey.references_table,
                        columns: foreignKey.references_columns,
                    },
                });
            }
        }

        if (section === 'indexes') {
            for (const index of outline.indexes) {
                nodes.push({
                    kind: 'index',
                    key: `${sectionKey}:${index.name}`,
                    name: index.name,
                    columns: index.columns,
                    unique: index.unique,
                });
            }
        }
    }

    return nodes;
}

/**
 * The tables of the database, searched by name, each one unfolding into its columns, keys and indexes before it is
 * opened. The tree is drawn as a flat list where only the visible lines exist, for databases with thousands of tables.
 */
export default function TableList({
    projectId,
    tables,
    current,
    openTables,
    schemaVersion,
    onOpen,
    onAction,
    onCreateTable,
    onOpenConsole,
    onImport,
    onExport,
    onColumnSelect,
    columnsOnly = false,
}: Props) {
    'use no memo';

    const { t } = useLaravelReactI18n();
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search.trim().toLowerCase());
    const [expanded, setExpanded] = useState<Set<string>>(() => new Set());
    const [openSections, setOpenSections] = useState<Set<string>>(
        () => new Set(),
    );
    const [outlines, setOutlines] = useState<Record<string, Outline>>({});
    const [menuTable, setMenuTable] = useState<string | null>(null);
    const loadedVersion = useRef(schemaVersion);
    const searchRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);

    const load = (table: string) => {
        setOutlines((currentOutlines) => ({
            ...currentOutlines,
            [table]: { status: 'loading' },
        }));

        requestTableData<TableOutline>(
            'get',
            TableBrowserController.outline.url({
                project: projectId,
                table: encodeURIComponent(table),
            }),
        )
            .then((outline) =>
                setOutlines((currentOutlines) => ({
                    ...currentOutlines,
                    [table]: { status: 'loaded', outline },
                })),
            )
            .catch((error: TableRequestError) =>
                setOutlines((currentOutlines) => ({
                    ...currentOutlines,
                    [table]: { status: 'error', message: error.first() },
                })),
            );
    };

    // The structure changed: the unfolded tables are read again, the others will be when unfolded.
    useEffect(() => {
        if (loadedVersion.current === schemaVersion) {
            return;
        }

        loadedVersion.current = schemaVersion;
        setOutlines({});
        expanded.forEach((table) => {
            if (tables.some((item) => item.name === table)) {
                load(table);
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [schemaVersion]);

    const toggleTable = (table: string) => {
        const next = new Set(expanded);

        if (next.has(table)) {
            next.delete(table);
        } else {
            next.add(table);

            if (!outlines[table] || outlines[table].status === 'error') {
                load(table);
            }

            // The columns are what one unfolds a table for: they open with it the first time.
            if (![...openSections].some((key) => key.startsWith(`${table}:`))) {
                setOpenSections(new Set([...openSections, `${table}:columns`]));
            }
        }

        setExpanded(next);
    };

    const toggleSection = (key: string) => {
        const next = new Set(openSections);

        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }

        setOpenSections(next);
    };

    const visible =
        deferredSearch === ''
            ? tables
            : tables.filter((table) =>
                  table.name.toLowerCase().includes(deferredSearch),
              );

    const nodes: Node[] = [];

    for (const table of visible) {
        const isExpanded = expanded.has(table.name);

        nodes.push({
            kind: 'table',
            key: table.name,
            table,
            expanded: isExpanded,
        });

        if (!isExpanded) {
            continue;
        }

        const outline = outlines[table.name];

        if (!outline || outline.status === 'loading') {
            nodes.push({ kind: 'loading', key: `${table.name}:loading` });
        } else if (outline.status === 'error') {
            nodes.push({
                kind: 'error',
                key: `${table.name}:error`,
                message: outline.message,
            });
        } else {
            nodes.push(
                ...outlineNodes(
                    table.name,
                    outline.outline,
                    openSections,
                    columnsOnly,
                ),
            );
        }
    }

    const virtualizer = useVirtualizer({
        count: nodes.length,
        getScrollElement: () => listRef.current,
        estimateSize: (index) =>
            nodes[index].kind === 'table' ? TABLE_HEIGHT : DETAIL_HEIGHT,
        overscan: 12,
        getItemKey: (index) => nodes[index].key,
    });

    // The character is matched rather than the physical key, and Shift is ignored: on an AZERTY keyboard « / » is
    // typed with Shift, which the hotkey would otherwise require to be absent.
    useHotkeys('/', () => searchRef.current?.focus(), {
        preventDefault: true,
        useKey: true,
        ignoreModifiers: true,
        ignoreEventWhen: (event) =>
            (event.ctrlKey || event.metaKey || event.altKey) &&
            !event.getModifierState('AltGraph'),
    });

    useEffect(() => {
        const index = nodes.findIndex(
            (node) => node.kind === 'table' && node.table.name === current,
        );

        if (index >= 0) {
            virtualizer.scrollToIndex(index, { align: 'auto' });
        }
        // Only when another table is opened: scrolling while the user searches would move the list under the pointer.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [current]);

    const openFirstMatch = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' && visible[0]) {
            onOpen(visible[0].name);
        } else if (event.key === 'Escape') {
            setSearch('');
            searchRef.current?.blur();
        }
    };

    const renderNode = (node: Node) => {
        switch (node.kind) {
            case 'table': {
                const { table } = node;
                const selected = table.name === current;
                const opened = openTables.includes(table.name);

                return (
                    <div
                        data-table={table.name}
                        className={cn(
                            'group/table flex h-full items-center rounded-md text-sm transition-colors hover:bg-muted',
                            selected && 'bg-muted',
                        )}
                    >
                        <button
                            type="button"
                            onClick={() => toggleTable(table.name)}
                            aria-expanded={node.expanded}
                            aria-label={node.expanded ? t('tables.list.collapse', { table: table.name }) : t('tables.list.expand', { table: table.name })}
                            className="flex h-full w-6 shrink-0 items-center justify-center rounded-l-md text-muted-foreground hover:text-foreground"
                        >
                            <ChevronRightIcon
                                className={cn(
                                    'size-3.5 transition-transform motion-reduce:transition-none',
                                    node.expanded && 'rotate-90',
                                )}
                            />
                        </button>
                        <button
                            type="button"
                            onClick={() => onOpen(table.name)}
                            aria-current={selected ? 'page' : undefined}
                            title={table.name}
                            className={cn(
                                'flex h-full min-w-0 flex-1 items-center gap-2 pr-2 text-left',
                                selected
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground group-hover/table:text-foreground',
                            )}
                        >
                            <Table2Icon
                                className={cn(
                                    'size-4 shrink-0',
                                    selected
                                        ? 'text-sidebar-primary'
                                        : 'text-muted-foreground',
                                )}
                            />
                            <span className="min-w-0 flex-1 truncate">
                                {table.name}
                            </span>
                            {opened && !selected && (
                                <span
                                    className="size-1.5 shrink-0 rounded-full bg-muted-foreground/60"
                                    aria-label={t('tables.list.open_in_tab')}
                                />
                            )}
                            {!table.agentAccessEnabled && (
                                <BotOffIcon
                                    className="size-3.5 shrink-0 text-muted-foreground"
                                    aria-label={t('tables.list.agent_disabled')}
                                />
                            )}
                            {!table.hasPrimaryKey && (
                                <span
                                    title={t('tables.list.no_primary_key')}
                                    className="relative shrink-0 text-muted-foreground/70"
                                >
                                    <KeyRoundIcon className="size-3.5" />
                                    <span className="absolute top-1/2 left-1/2 h-px w-4 -translate-1/2 -rotate-45 bg-current" />
                                </span>
                            )}
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {table.columns}
                            </span>
                        </button>
                    </div>
                );
            }
            case 'loading':
                return (
                    <div className="flex h-full items-center pl-8">
                        <span className="h-2.5 w-28 animate-pulse rounded-sm bg-muted motion-reduce:animate-none" />
                    </div>
                );
            case 'error':
                return (
                    <p
                        className="flex h-full items-center truncate pl-8 text-xs text-destructive"
                        title={node.message}
                    >
                        {node.message}
                    </p>
                );
            case 'section':
                return (
                    <button
                        type="button"
                        onClick={() => toggleSection(node.key)}
                        aria-expanded={node.open}
                        className="flex h-full w-full items-center gap-1.5 rounded-md pr-2 pl-6 text-left text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        <ChevronRightIcon
                            className={cn(
                                'size-3 shrink-0 transition-transform motion-reduce:transition-none',
                                node.open && 'rotate-90',
                            )}
                        />
                        <span className="flex-1">
                            {t(SECTION_LABELS[node.section])}
                        </span>
                        <span className="tabular-nums">{node.count}</span>
                    </button>
                );
            case 'column': {
                const Row = onColumnSelect ? 'button' : 'div';

                return (
                    <Row
                        {...(onColumnSelect && {
                            type: 'button' as const,
                            onClick: () =>
                                onColumnSelect(node.table, node.name),
                        })}
                        className={cn(
                            'flex h-full w-full items-center gap-1.5 pr-2 text-left text-xs',
                            columnsOnly ? 'pl-8' : 'pl-11',
                            onColumnSelect &&
                                'rounded-md transition-colors hover:bg-muted',
                        )}
                        title={`${node.name} ${node.type}${node.nullable ? `, ${t('tables.grid.nullable')}` : ''}${node.defaultValue !== null ? `, ${t('tables.list.default', { value: node.defaultValue })}` : ''}`}
                    >
                        {node.primary ? (
                            <KeyRoundIcon
                                className="size-3 shrink-0 text-warning"
                                aria-label={t('tables.designer.columns.primary_key')}
                            />
                        ) : node.foreign ? (
                            <LinkIcon
                                className="size-3 shrink-0 text-muted-foreground"
                                aria-label={t('tables.list.foreign_key')}
                            />
                        ) : (
                            <span className="size-3 shrink-0" />
                        )}
                        <span className="min-w-0 truncate text-foreground/90">
                            {node.name}
                        </span>
                        <span className="ml-auto max-w-[45%] shrink-0 truncate font-mono text-[11px] text-muted-foreground">
                            {node.type}
                            {node.nullable ? '?' : ''}
                        </span>
                    </Row>
                );
            }
            case 'key':
                return (
                    <div
                        className="flex h-full items-center gap-1.5 pr-2 pl-11 text-xs"
                        title={
                            node.target
                                ? `${node.label} : ${node.columns.join(', ')} → ${node.target.table} (${node.target.columns.join(', ')})`
                                : t('tables.list.primary_key_columns', { columns: node.columns.join(', ') })
                        }
                    >
                        {node.target ? (
                            <LinkIcon className="size-3 shrink-0 text-muted-foreground" />
                        ) : (
                            <KeyRoundIcon className="size-3 shrink-0 text-warning" />
                        )}
                        <span className="min-w-0 truncate font-mono text-[11px]">
                            {node.columns.join(', ')}
                        </span>
                        {node.target && (
                            <button
                                type="button"
                                onClick={() => onOpen(node.target!.table)}
                                className="ml-auto min-w-0 shrink truncate text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                            >
                                → {node.target.table}
                            </button>
                        )}
                    </div>
                );
            case 'index':
                return (
                    <div
                        className="flex h-full items-center gap-1.5 pr-2 pl-11 text-xs"
                        title={`${node.name} (${node.columns.join(', ')})${node.unique ? ', unique' : ''}`}
                    >
                        <ListTreeIcon className="size-3 shrink-0 text-muted-foreground" />
                        <span className="min-w-0 truncate font-mono text-[11px]">
                            {node.columns.join(', ')}
                        </span>
                        {node.unique && (
                            <span className="ml-auto shrink-0 text-[11px] text-muted-foreground">
                                {t('tables.records.unique')}
                            </span>
                        )}
                    </div>
                );
            case 'empty':
                return (
                    <p
                        className={cn(
                            'flex h-full items-center text-xs text-muted-foreground',
                            columnsOnly ? 'pl-8' : 'pl-11',
                        )}
                    >
                        {t(node.text)}
                    </p>
                );
        }
    };

    return (
        <nav
            aria-label={t('tables.list.title')}
            className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden"
        >
            <div className="flex min-w-0 items-center gap-0.5 px-2 pt-2 pb-1.5">
                <h2 className="flex min-w-0 flex-1 items-baseline gap-2 truncate pl-1 text-sm font-medium">
                    {t('projects.menu.tables')}
                    <span className="text-xs font-normal text-muted-foreground tabular-nums">
                        {tables.length}
                    </span>
                </h2>
                {onCreateTable && (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={onCreateTable}
                        aria-label={t('tables.create')}
                        tooltip={t('tables.create')}
                    >
                        <PlusIcon />
                    </Button>
                )}
                {onOpenConsole && (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={onOpenConsole}
                        aria-label={t('tables.list.open_console')}
                        tooltip={t('tables.list.open_console')}
                    >
                        <SquareTerminalIcon />
                    </Button>
                )}
                {(onImport || onExport) && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label={t('tables.list.import_export')}
                                tooltip={t('tables.list.import_export')}
                            >
                                <EllipsisVerticalIcon />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            {onImport && (
                                <DropdownMenuItem onSelect={onImport}>
                                    <UploadIcon />
                                    {t('tables.import.title')}
                                </DropdownMenuItem>
                            )}
                            {onExport && (
                                <DropdownMenuItem onSelect={onExport}>
                                    <DownloadIcon />
                                    {t('tables.list.export_database')}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            <label className="relative mx-2 mb-2 flex items-center">
                <span className="sr-only">{t('shortcuts.search_table')}</span>
                <SearchIcon className="pointer-events-none absolute left-2.5 size-4 text-muted-foreground" />
                <input
                    ref={searchRef}
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onKeyDown={openFirstMatch}
                    placeholder={t('common.search')}
                    className="h-8 w-full rounded-md border border-input bg-transparent pr-8 pl-8 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                />
                {search === '' && (
                    <Kbd className="pointer-events-none absolute right-2">
                        /
                    </Kbd>
                )}
            </label>

            <ContextMenu modal={false}>
                <ContextMenuTrigger asChild>
                    <div
                        ref={listRef}
                        className="min-h-0 flex-1 overflow-y-auto px-2 pb-2"
                        onContextMenu={(event) => {
                            const row = (
                                event.target as HTMLElement
                            ).closest<HTMLElement>('[data-table]');

                            if (row && onAction) {
                                setMenuTable(row.dataset.table ?? null);
                            } else {
                                event.preventDefault();
                            }
                        }}
                    >
                        {visible.length === 0 ? (
                            <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                                {tables.length === 0
                                    ? t('tables.list.empty')
                                    : t('tables.list.no_match')}
                            </p>
                        ) : (
                            <ul
                                className="relative"
                                style={{ height: virtualizer.getTotalSize() }}
                            >
                                {virtualizer.getVirtualItems().map((item) => (
                                    <li
                                        key={item.key}
                                        className="absolute inset-x-0"
                                        style={{
                                            top: item.start,
                                            height: item.size,
                                        }}
                                    >
                                        {renderNode(nodes[item.index])}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </ContextMenuTrigger>
                {menuTable && onAction && (
                    <TableContextMenu
                        table={menuTable}
                        onAction={(action) => onAction(action, menuTable)}
                    />
                )}
            </ContextMenu>
        </nav>
    );
}

function TableContextMenu({
    table,
    onAction,
}: {
    table: string;
    onAction: (action: TableAction) => void;
}) {
    const { t } = useLaravelReactI18n();
    // The actions the privileges of the user on the table refuse are left out, as the server would refuse them.
    const { canOnProject } = usePrivileges();
    const can = (privilege: Privilege) => canOnProject(privilege, table);

    return (
        <ContextMenuContent className="w-56">
            <ContextMenuItem onSelect={() => onAction('open')}>
                <Table2Icon />
                {t('tables.list.open')}
            </ContextMenuItem>
            {can('alter') && (
                <ContextMenuItem onSelect={() => onAction('design')}>
                    <PencilRulerIcon />
                    {t('tables.workspace.edit_structure')}
                </ContextMenuItem>
            )}
            {can('alter') && (
                <ContextMenuItem onSelect={() => onAction('rename')}>
                    <TextCursorInputIcon />
                    {t('tables.workspace.rename')}
                </ContextMenuItem>
            )}
            {canOnProject('create') && (
                <ContextMenuItem onSelect={() => onAction('duplicate')}>
                    <CopyPlusIcon />
                    {t('tables.workspace.duplicate')}
                </ContextMenuItem>
            )}
            <ContextMenuItem
                onSelect={() => navigator.clipboard.writeText(table)}
            >
                <CopyIcon />
                {t('tables.list.copy_name')}
            </ContextMenuItem>
            <ContextMenuSeparator />
            {can('import') && can('insert') && (
                <ContextMenuItem onSelect={() => onAction('import')}>
                    <FileUpIcon />
                    {t('tables.workspace.import_csv')}
                </ContextMenuItem>
            )}
            {can('export') && (
                <ContextMenuItem onSelect={() => onAction('export')}>
                    <DownloadIcon />
                    {t('tables.workspace.export')}
                </ContextMenuItem>
            )}
            {can('drop') && (
                <>
                    <ContextMenuSeparator />
                    <ContextMenuItem
                        variant="destructive"
                        onSelect={() => onAction('truncate')}
                    >
                        <EraserIcon />
                        {t('tables.list.truncate')}
                    </ContextMenuItem>
                    <ContextMenuItem
                        variant="destructive"
                        onSelect={() => onAction('drop')}
                    >
                        <Trash2Icon />
                        {t('common.delete')}
                    </ContextMenuItem>
                </>
            )}
        </ContextMenuContent>
    );
}
