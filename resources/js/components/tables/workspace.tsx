import type { UIMessage } from 'ai';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ChartColumnIcon,
    ChevronDownIcon,
    CopyPlusIcon,
    DownloadIcon,
    EllipsisIcon,
    EraserIcon,
    FileUpIcon,
    FilterIcon,
    FormInputIcon,
    GaugeIcon,
    MessageSquareTextIcon,
    MessagesSquareIcon,
    PencilRulerIcon,
    PlusIcon,
    RefreshCwIcon,
    SearchXIcon,
    Table2Icon,
    TextCursorInputIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { toast } from 'sonner';
import { describeKey } from '@/components/ai/artifacts/record/format';
import type { RecordLocation } from '@/components/ai/artifacts/record/format';
import { formatInteger } from '@/components/ai/artifacts/table-profile/format';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import AiFilterInput from '@/components/tables/assistant/ai-filter-input';
import IndexesPanel from '@/components/tables/assistant/indexes-panel';
import InsightsPanel from '@/components/tables/assistant/insights-panel';
import QuickChatPanel from '@/components/tables/assistant/quick-chat-panel';
import DataGrid from '@/components/tables/data-grid/data-grid';
import type { GridRow } from '@/components/tables/data-grid/data-grid';
import { cellRawText } from '@/components/tables/data-grid/format';
import type { GridActions } from '@/components/tables/data-grid/grid-context-menu';
import { useColumnWidths } from '@/components/tables/data-grid/use-column-widths';
import DefinitionView from '@/components/tables/definition-view';
import FilterBar, { createFilter } from '@/components/tables/filter-bar';
import {
    buildGridRows,
    editCell,
    keyOf,
    truncated,
} from '@/components/tables/grid-rows';
import InsertRecordPanel from '@/components/tables/insert-record-panel';
import PaginationBar from '@/components/tables/pagination-bar';
import PendingChangesBar from '@/components/tables/pending-changes-bar';
import RecordPanel from '@/components/tables/record-panel';
import SelectionActionBar from '@/components/tables/selection-action-bar';
import StructureView from '@/components/tables/structure-view';
import type { TableAction } from '@/components/tables/table-list';
import type {
    Filter,
    FilterMatch,
    FilterSuggestion,
    TableColumn,
    TableStructure,
    TableSummary,
    TableView,
} from '@/components/tables/types';
import { useTableStructure } from '@/components/tables/use-table-structure';
import { newFilterId, nextSorts, viewQuery } from '@/components/tables/view';
import ViewQueryButton from '@/components/tables/view-query-button';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConversation } from '@/hooks/use-conversation';
import { usePrivileges } from '@/hooks/use-privileges';
import { useTableRowCount, useTableRows } from '@/hooks/use-table-rows';
import { cn } from '@/lib/utils';
import {
    useTableChangesApi,
    useTableChangesStore,
} from '@/store/use-table-changes-store';
import { useTableGridApi } from '@/store/use-table-grid-store';
import type { CellValue, RecordKey } from '@/types/agent';
import { DefaultDrivers } from '@/types/databases';
import type { Project } from '@/types/models/project';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';

/**
 * A request to show some rows of the table: the tab opens on them, for instance when a foreign key is followed.
 */
export type TableFocus = {
    nonce: number;
    filters: Filter[];
    /**
     * The row to open in the detail panel, once the structure tells it is designated by its primary key.
     */
    record: RecordKey | null;
};

type Props = {
    project: Project;
    table: string;
    summary: TableSummary;
    initialView: TableView;
    /**
     * The structure sent with the page, undefined while the page is still loading it.
     */
    initialStructure: TableStructure | null | undefined;
    /**
     * Whether the tab is the one shown: the hidden tabs keep their state but leave the address and the screen alone.
     */
    active: boolean;
    focus: TableFocus | null;
    /**
     * Changes when the structure of the database changed: the structure of the table is read again.
     */
    schemaVersion: number;
    /**
     * Changes when the rows changed outside the grid, by an import or once the table is emptied.
     */
    dataVersion: number;
    /**
     * Changes when the address must be written again, after Inertia replaced it with the one of a reload.
     */
    addressVersion: number;
    onOpenTable: (table: string, focus?: Omit<TableFocus, 'nonce'>) => void;
    onAction: (action: TableAction, table: string) => void;
    onOpenConsole: (sql: string) => void;
    /**
     * Called once the structure of the table changed from the tab, to read the structure of the database again.
     */
    onSchemaChanged: () => void;
};

type Tab = 'data' | 'structure' | 'definition';

type SidePanel =
    | { type: 'record'; location: RecordLocation }
    | { type: 'insights' }
    | { type: 'indexes' }
    | { type: 'chat' }
    | { type: 'insert'; values?: Record<string, CellValue> }
    | null;

/**
 * Rows sent at most to the chat with a question about the selection.
 */
const CHAT_ROWS_LIMIT = 20;

const AGENT_DISABLED_REASON = 'tables.workspace.agent_disabled';

/**
 * The tab of a table: its rows, its structure and its definition, the changes waiting to be applied, the rows ticked
 * for an action on all of them, and the AI helpers that read the table or its view.
 */
export default function Workspace({
    project,
    table,
    summary,
    initialView,
    initialStructure,
    active,
    focus,
    schemaVersion,
    dataVersion,
    addressVersion,
    onOpenTable,
    onAction,
    onOpenConsole,
    onSchemaChanged,
}: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const [view, setView] = useState<TableView>(initialView);
    const [localRefresh, setLocalRefresh] = useState(0);
    const refreshKey = localRefresh + dataVersion;
    const setRefreshKey = (next: number) => setLocalRefresh(next - dataVersion);
    const structure = useTableStructure(
        project.id,
        table,
        initialStructure,
        schemaVersion,
    );
    const [tab, setTab] = useState<Tab>('data');
    const [panel, setPanel] = useState<SidePanel>(null);
    const [explanation, setExplanation] = useState<string | null>(null);
    const [quickChatMessages, setQuickChatMessages] = useState<UIMessage[]>([]);
    const [filtersOpen, setFiltersOpen] = useState(
        () => view.filters.length > 0,
    );

    const { startConversation } = useConversation();

    const updates = useTableChangesStore((state) => state.updates);
    const inserts = useTableChangesStore((state) => state.inserts);
    const deletes = useTableChangesStore((state) => state.deletes);
    const errors = useTableChangesStore((state) => state.errors);
    const changes = useTableChangesApi().getState();
    const gridApi = useTableGridApi();

    const rowsState = useTableRows(project.id, table, view, refreshKey);
    const count = useTableRowCount(project.id, table, view, refreshKey);

    const columns = useMemo(() => structure?.columns ?? [], [structure]);
    const primaryKey = useMemo(() => structure?.primaryKey ?? [], [structure]);
    const { canOnProject } = usePrivileges();
    // A saved row is only reached through its primary key: changed with UPDATE on the table, deleted with DELETE.
    const hasPrimaryKey = primaryKey.length > 0;
    const canEditSavedRows = hasPrimaryKey && canOnProject('update', table);
    const canDeleteSavedRows = hasPrimaryKey && canOnProject('delete', table);
    const canInsert = canOnProject('insert', table);
    const canExport = canOnProject('export', table);
    // The actions on the whole table the privileges of the user allow, like in the context menu of the list.
    const tableActions = (
        [
            { action: 'design', label: 'tables.workspace.edit_structure', icon: PencilRulerIcon, group: 'structure', allowed: canOnProject('alter', table) },
            { action: 'rename', label: 'tables.workspace.rename', icon: TextCursorInputIcon, group: 'structure', allowed: canOnProject('alter', table) },
            { action: 'duplicate', label: 'tables.workspace.duplicate', icon: CopyPlusIcon, group: 'structure', allowed: canOnProject('create') },
            { action: 'import', label: 'tables.workspace.import_csv', icon: FileUpIcon, group: 'files', allowed: canOnProject('import', table) && canInsert },
            { action: 'export', label: 'tables.workspace.export_sql', icon: DownloadIcon, group: 'files', allowed: canExport },
            { action: 'truncate', label: 'tables.truncate.confirm', icon: EraserIcon, group: 'danger', allowed: canOnProject('drop', table) },
            { action: 'drop', label: 'tables.drop.confirm', icon: Trash2Icon, group: 'danger', allowed: canOnProject('drop', table) },
        ] satisfies { action: TableAction; label: string; icon: LucideIcon; group: string; allowed: boolean }[]
    ).filter((item) => item.allowed);
    const { widths, resize } = useColumnWidths(project.id, table, columns);
    const disabledReason = summary.agentAccessEnabled
        ? null
        : t(AGENT_DISABLED_REASON);

    // The address keeps the view of the shown tab, so a reload or a shared link opens the same rows.
    useEffect(() => {
        if (!active) {
            return;
        }

        window.history.replaceState(
            window.history.state,
            '',
            TableBrowserController.index.url(
                { project: project.id, table: encodeURIComponent(table) },
                { query: viewQuery(view) },
            ),
        );
    }, [active, addressVersion, project.id, table, view]);

    // Row numbers change meaning with the page: the selection would point at another row.
    useEffect(() => {
        gridApi.getState().select(null);
        gridApi.getState().clearSelection();
    }, [gridApi, view.page, view.perPage, view.sorts, view.filters]);

    // A followed reference shows the rows it points at, and the row itself once it is known to be its primary key.
    const appliedFocus = useRef<number | null>(null);
    const pendingRecord = useRef<RecordKey | null>(null);

    useEffect(() => {
        if (!focus || appliedFocus.current === focus.nonce) {
            return;
        }

        appliedFocus.current = focus.nonce;
        pendingRecord.current = focus.record;
        setView((current) => ({
            ...current,
            filters: focus.filters,
            match: 'all',
            page: 1,
        }));
        setFiltersOpen(focus.filters.length > 0);
        setTab('data');
    }, [focus]);

    useEffect(() => {
        const key = pendingRecord.current;

        if (!key || !structure) {
            return;
        }

        pendingRecord.current = null;

        const columnsOfKey = Object.keys(key);

        if (
            columnsOfKey.length === structure.primaryKey.length &&
            columnsOfKey.every((column) =>
                structure.primaryKey.includes(column),
            )
        ) {
            setPanel({ type: 'record', location: { table, key } });
        }
    }, [structure, focus, table]);

    const changeView = (next: Partial<TableView>) =>
        setView((current) => ({ ...current, page: 1, ...next }));

    const changeFilters = (filters: Filter[], match: FilterMatch) => {
        changeView({ filters, match });

        if (filters.length === 0) {
            setExplanation(null);
        }
    };

    const applySuggestion = (suggestion: FilterSuggestion) => {
        if (suggestion.filters.length === 0) {
            setExplanation(suggestion.explanation);
            setFiltersOpen(true);

            return;
        }

        changeView({
            filters: suggestion.filters.map((filter) => ({
                ...filter,
                id: newFilterId(),
                value: filter.value === null ? null : String(filter.value),
            })),
            match: suggestion.match,
        });
        setExplanation(suggestion.explanation);
        setFiltersOpen(true);
    };

    const page = rowsState.page;
    const offset =
        ((page?.page ?? view.page) - 1) * (page?.perPage ?? view.perPage);

    const gridRows = useMemo<GridRow[]>(
        () =>
            buildGridRows(page?.rows ?? [], offset, primaryKey, {
                inserts,
                updates,
                deletes,
                errors,
            }),
        [page, inserts, updates, deletes, errors, primaryKey, offset],
    );

    const setCellValue = (
        row: GridRow,
        column: TableColumn,
        value: CellValue,
    ) => editCell(changes, primaryKey, row, column, value);

    const askChat = (text: string) =>
        startConversation({ text: truncated(text), files: [] });

    const addRow = (values: Record<string, CellValue> = {}) => {
        setTab('data');
        changes.addInsert(values);

        const firstEditable = columns.findIndex(
            (column) => column.editable && !column.auto_increment,
        );

        // The new row is drawn first: its first cell to fill opens once the grid has rendered it.
        requestAnimationFrame(() => {
            gridApi
                .getState()
                .select({ row: 0, column: Math.max(0, firstEditable) });

            if (firstEditable >= 0 && Object.keys(values).length === 0) {
                gridApi.getState().startEditing();
            }
        });
    };

    /**
     * The values of a row that a copy of it keeps: not the identifiers the database gives.
     */
    const copiedValues = (row: GridRow): Record<string, CellValue> =>
        Object.fromEntries(
            columns
                .filter(
                    (column) =>
                        column.editable &&
                        !column.auto_increment &&
                        !(
                            column.primary &&
                            canEditSavedRows &&
                            column.kind === 'integer'
                        ),
                )
                .map((column) => [
                    column.name,
                    row.values[column.name] ?? null,
                ]),
        );

    const followReference = (row: GridRow, column: TableColumn) => {
        const value = row.values[column.name] ?? null;

        if (!column.references || value === null) {
            return;
        }

        onOpenTable(column.references.table, {
            filters: [
                {
                    id: newFilterId(),
                    column: column.references.column,
                    operator: '=',
                    value: cellRawText(value),
                },
            ],
            record: { [column.references.column]: value },
        });
    };

    const actions: GridActions = {
        copyValue: (row, column) => {
            navigator.clipboard
                .writeText(cellRawText(row.values[column.name] ?? null))
                .catch(() => null);
        },
        copyRow: (row) => {
            navigator.clipboard
                .writeText(JSON.stringify(row.values, null, 2))
                .then(() =>
                    toast.success(t('tables.workspace.row_copied'), { duration: 1500 }),
                );
        },
        setValue: setCellValue,
        filterByValue: (column, value, exclude) => {
            const filter: Filter =
                value === null
                    ? {
                          id: newFilterId(),
                          column: column.name,
                          operator: exclude ? 'is_not_null' : 'is_null',
                          value: null,
                      }
                    : {
                          id: newFilterId(),
                          column: column.name,
                          operator: exclude ? '!=' : '=',
                          value: cellRawText(value),
                      };

            changeView({ filters: [...view.filters, filter] });
            setFiltersOpen(true);
        },
        openRow: (row) => {
            if (row.state === 'inserted' || !row.original) {
                toast.info(
                    t('tables.workspace.row_not_saved'),
                );

                return;
            }

            if (!hasPrimaryKey) {
                toast.info(
                    t('tables.workspace.row_without_key'),
                );

                return;
            }

            setPanel({
                type: 'record',
                location: { table, key: keyOf(row.original, primaryKey) },
            });
        },
        duplicateRow: canInsert ? (row) => addRow(copiedValues(row)) : undefined,
        toggleDelete: (row) => {
            if (row.state === 'inserted' && row.insertId) {
                changes.removeInsert(row.insertId);
            } else if (row.rowKey && row.original) {
                changes.toggleDelete(
                    row.rowKey,
                    keyOf(row.original, primaryKey),
                );
            }
        },
        revertRow: (row) => row.rowKey && changes.revertRow(row.rowKey),
        askChat: (row) =>
            askChat(
                row.original && hasPrimaryKey
                    ? t('tables.workspace.ask_row_with_key', { table, key: describeKey(keyOf(row.original, primaryKey)), values: JSON.stringify(row.values, null, 2) })
                    : t('tables.workspace.ask_row', { table, values: JSON.stringify(row.values, null, 2) }),
            ),
        followReference,
    };

    const selectionActions = {
        onDelete: (rows: GridRow[]) => {
            const restore = rows.every((row) => row.state === 'deleted');

            for (const row of rows) {
                if (row.state === 'inserted' && row.insertId) {
                    changes.removeInsert(row.insertId);
                } else if (
                    row.rowKey &&
                    row.original &&
                    (row.state === 'deleted') === restore
                ) {
                    changes.toggleDelete(
                        row.rowKey,
                        keyOf(row.original, primaryKey),
                    );
                }
            }
        },
        onDuplicate: (rows: GridRow[]) => {
            setTab('data');

            for (const row of [...rows].reverse()) {
                changes.addInsert(copiedValues(row));
            }

            toast.success(
                tChoice('tables.workspace.rows_added', rows.length),
                {
                    description: t('tables.workspace.rows_added_description'),
                },
            );
        },
        onRevert: (rows: GridRow[]) => {
            for (const row of rows) {
                if (row.rowKey) {
                    changes.revertRow(row.rowKey);
                }
            }
        },
        onSetValue: (
            rows: GridRow[],
            column: TableColumn,
            value: CellValue,
        ) => {
            for (const row of rows) {
                if (row.state !== 'deleted') {
                    setCellValue(row, column, value);
                }
            }
        },
        onAskChat: (rows: GridRow[]) =>
            askChat(
                (rows.length > CHAT_ROWS_LIMIT
                    ? t('tables.workspace.ask_rows_limited', { table, count: rows.length, limit: CHAT_ROWS_LIMIT })
                    : t('tables.workspace.ask_rows', { table, count: rows.length })) +
                    `\n\n${JSON.stringify(
                        rows.slice(0, CHAT_ROWS_LIMIT).map((row) => row.values),
                        null,
                        2,
                    )}`,
            ),
    };

    const activeFilters = view.filters.length;
    const exportUrl = (format: 'csv' | 'json') =>
        TableBrowserController.export.url(
            { project: project.id, table: encodeURIComponent(table) },
            { query: { ...viewQuery(view, { withPage: false }), format } },
        );

    const askAboutTable = () => {
        const filters = view.filters.map((filter) =>
            `${filter.column} ${filter.operator} ${filter.value ?? ''}`.trim(),
        );

        askChat(
            t('tables.workspace.ask_table', { table }) +
                (filters.length
                    ? `\n${t('tables.workspace.ask_table_filters', { filters: filters.join(view.match === 'any' ? ` ${t('tables.filters.or')} ` : ` ${t('tables.filters.and')} `) })}`
                    : ''),
        );
    };

    const panelTitle =
        panel?.type === 'record'
            ? `${panel.location.table}, ${describeKey(panel.location.key)}`
            : panel?.type === 'insights'
              ? t('tables.workspace.insights')
              : panel?.type === 'insert'
                ? t('tables.workspace.new_row', { table })
                : panel?.type === 'chat'
                  ? t('tables.workspace.quick_chat_title', { table })
                  : t('tables.workspace.advised_indexes');

    const { defaultLayout, onLayoutChanged } = useDefaultLayout({
        id: 'project-tables-workspace',
        storage: localStorage,
        panelIds: panel
            ? ['table-content', 'table-side-panel']
            : ['table-content'],
    });

    return (
        <ResizablePanelGroup
            orientation="horizontal"
            defaultLayout={defaultLayout}
            onLayoutChanged={onLayoutChanged}
            className="min-h-0 flex-1 gap-2"
        >
            <ResizablePanel
                id="table-content"
                minSize="420px"
                className="flex min-w-0 flex-col overflow-hidden rounded-lg rounded-t-none border bg-background"
            >
                <header className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-3 pt-2.5">
                    <div className="flex min-w-0 items-baseline gap-2 pb-2.5">
                        <h1 className="truncate text-base font-medium">
                            {table}
                        </h1>
                        <span className="shrink-0 text-sm text-muted-foreground tabular-nums">
                            {count
                                ? tChoice(count.estimated ? 'tables.workspace.about_rows' : 'tables.counts.rows', count.total, { count: formatInteger(count.total) })
                                : ''}
                        </span>
                    </div>

                    <Tabs
                        value={tab}
                        onValueChange={(value) => setTab(value as Tab)}
                        className="self-end"
                    >
                        <TabsList variant="line" className="h-9">
                            <TabsTrigger value="data">{t('tables.workspace.tabs.data')}</TabsTrigger>
                            <TabsTrigger value="structure">
                                {t('tables.workspace.tabs.structure')}
                            </TabsTrigger>
                            <TabsTrigger value="definition">DDL</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <div className="ml-auto flex items-center gap-1 pb-2">
                        <Button
                            variant={
                                panel?.type === 'insights'
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            size="sm"
                            onClick={() =>
                                setPanel(
                                    panel?.type === 'insights'
                                        ? null
                                        : { type: 'insights' },
                                )
                            }
                            aria-pressed={panel?.type === 'insights'}
                        >
                            <ChartColumnIcon />
                            {t('tables.workspace.analyze')}
                        </Button>
                        <Button
                            variant={
                                panel?.type === 'indexes'
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            size="sm"
                            onClick={() =>
                                setPanel(
                                    panel?.type === 'indexes'
                                        ? null
                                        : { type: 'indexes' },
                                )
                            }
                            aria-pressed={panel?.type === 'indexes'}
                        >
                            <GaugeIcon />
                            {t('tables.workspace.indexes')}
                        </Button>
                        <Button
                            variant={
                                panel?.type === 'chat' ? 'secondary' : 'ghost'
                            }
                            size="sm"
                            onClick={() =>
                                setPanel(
                                    panel?.type === 'chat'
                                        ? null
                                        : { type: 'chat' },
                                )
                            }
                            aria-pressed={panel?.type === 'chat'}
                            tooltip={t('tables.workspace.quick_chat_tooltip')}
                        >
                            <MessagesSquareIcon />
                            {t('tables.workspace.quick_chat')}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={askAboutTable}
                        >
                            <MessageSquareTextIcon />
                            {t('tables.workspace.ask_chat')}
                        </Button>
                        {tableActions.length > 0 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label={t('tables.workspace.actions')}
                                        tooltip={t('tables.workspace.actions')}
                                    >
                                        <EllipsisIcon />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56">
                                    {tableActions.map((item, index) => (
                                        <Fragment key={item.action}>
                                            {index > 0 && item.group !== tableActions[index - 1].group && (
                                                <DropdownMenuSeparator />
                                            )}
                                            <DropdownMenuItem
                                                variant={item.group === 'danger' ? 'destructive' : undefined}
                                                onSelect={() => onAction(item.action, table)}
                                            >
                                                <item.icon />
                                                {t(item.label)}
                                            </DropdownMenuItem>
                                        </Fragment>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </header>

                {tab === 'data' && (
                    <>
                        <div className="flex flex-wrap items-start gap-2 border-b px-3 py-2">
                            <Button
                                variant={
                                    filtersOpen || activeFilters
                                        ? 'secondary'
                                        : 'outline'
                                }
                                size="sm"
                                onClick={() => {
                                    if (activeFilters === 0 && columns.length) {
                                        changeView({
                                            filters: [createFilter(columns)],
                                        });
                                        setFiltersOpen(true);
                                    } else {
                                        setFiltersOpen(!filtersOpen);
                                    }
                                }}
                                aria-expanded={filtersOpen}
                                disabled={!structure}
                            >
                                <FilterIcon />
                                Filtres
                                {activeFilters > 0 && (
                                    <span className="tabular-nums">
                                        {activeFilters}
                                    </span>
                                )}
                            </Button>

                            <AiFilterInput
                                projectId={project.id}
                                table={table}
                                disabledReason={disabledReason}
                                onSuggestion={applySuggestion}
                            />

                            <div className="flex items-center gap-1">
                                <ViewQueryButton
                                    sql={page?.sql ?? null}
                                    active={active}
                                    onOpenInConsole={onOpenConsole}
                                />
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() =>
                                        setRefreshKey(refreshKey + 1)
                                    }
                                    aria-label={t('tables.workspace.reload_rows')}
                                    tooltip={t('tables.workspace.reload_rows')}
                                >
                                    <RefreshCwIcon
                                        className={cn(
                                            rowsState.loading &&
                                                'animate-spin motion-reduce:animate-none',
                                        )}
                                    />
                                </Button>
                                {canExport && (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm">
                                                <DownloadIcon />
                                                {t('tables.workspace.export')}
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            className="w-64"
                                        >
                                            <DropdownMenuItem asChild>
                                                <a href={exportUrl('csv')} download>
                                                    CSV
                                                    <span className="ml-auto text-xs text-muted-foreground">
                                                        {activeFilters
                                                            ? t('tables.workspace.filtered_rows')
                                                            : t('tables.workspace.whole_table')}
                                                    </span>
                                                </a>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <a
                                                    href={exportUrl('json')}
                                                    download
                                                >
                                                    JSON
                                                    <span className="ml-auto text-xs text-muted-foreground">
                                                        {activeFilters
                                                            ? t('tables.workspace.filtered_rows')
                                                            : t('tables.workspace.whole_table')}
                                                    </span>
                                                </a>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                )}
                                {canInsert && (
                                    <ButtonGroup>
                                        <Button
                                            size="sm"
                                            onClick={() => addRow()}
                                            disabled={!structure}
                                            tooltip={t('tables.workspace.add_row_tooltip')}
                                        >
                                            <PlusIcon />
                                            {t('tables.workspace.add_row')}
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    size="icon-sm"
                                                    disabled={!structure}
                                                    aria-label={t('tables.workspace.other_ways')}
                                                >
                                                    <ChevronDownIcon />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent
                                                align="end"
                                                className="w-72"
                                            >
                                                <DropdownMenuItem
                                                    onSelect={() => addRow()}
                                                >
                                                    <Table2Icon />
                                                    <span className="flex flex-col">
                                                        {t('tables.workspace.in_grid')}
                                                        <span className="text-xs text-muted-foreground">
                                                            {t('tables.workspace.in_grid_description')}
                                                        </span>
                                                    </span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setPanel({ type: 'insert' })
                                                    }
                                                >
                                                    <FormInputIcon />
                                                    <span className="flex flex-col">
                                                        {t('tables.workspace.with_form')}
                                                        <span className="text-xs text-muted-foreground">
                                                            {t('tables.workspace.with_form_description')}
                                                        </span>
                                                    </span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </ButtonGroup>
                                )}
                            </div>
                        </div>

                        {filtersOpen && structure && (
                            <FilterBar
                                columns={columns}
                                filters={view.filters}
                                match={view.match}
                                explanation={explanation}
                                onChange={changeFilters}
                                onDismissExplanation={() =>
                                    setExplanation(null)
                                }
                            />
                        )}

                        {!canEditSavedRows && structure && (
                            <p className="border-b px-3 py-1.5 text-xs text-muted-foreground">
                                {primaryKey.length === 0
                                    ? t('tables.workspace.no_primary_key')
                                    : t('tables.workspace.no_update_privilege')}
                            </p>
                        )}

                        {rowsState.error ? (
                            <div className="flex flex-1 items-center justify-center p-6">
                                <EmptyState
                                    icon={SearchXIcon}
                                    title={t('tables.workspace.read_failed')}
                                    description={rowsState.error}
                                    actions={[
                                        {
                                            label: t('tables.workspace.clear_filters'),
                                            variant: 'outline',
                                            onClick: () =>
                                                changeView({
                                                    filters: [],
                                                    sorts: [],
                                                }),
                                        },
                                    ]}
                                />
                            </div>
                        ) : !structure ? (
                            <div className="flex flex-1 flex-col gap-px p-3">
                                {Array.from({ length: 12 }, (_, index) => (
                                    <Skeleton key={index} className="h-7" />
                                ))}
                            </div>
                        ) : (
                            <DataGrid
                                columns={columns}
                                rows={gridRows}
                                widths={widths}
                                sorts={view.sorts}
                                canEditSavedRows={canEditSavedRows}
                                canDeleteSavedRows={canDeleteSavedRows}
                                loading={rowsState.loading}
                                onSort={(column, additive) =>
                                    changeView({
                                        sorts: nextSorts(
                                            view.sorts,
                                            column,
                                            additive,
                                        ),
                                    })
                                }
                                onResize={resize}
                                onEdit={setCellValue}
                                actions={actions}
                                empty={
                                    activeFilters ? (
                                        <EmptyState
                                            icon={SearchXIcon}
                                            title={t('tables.workspace.no_match')}
                                            description={t('tables.workspace.no_match_description')}
                                            actions={[
                                                {
                                                    label: t('tables.workspace.clear_filters'),
                                                    variant: 'outline',
                                                    onClick: () =>
                                                        changeFilters(
                                                            [],
                                                            'all',
                                                        ),
                                                },
                                            ]}
                                        />
                                    ) : (
                                        <EmptyState
                                            icon={Table2Icon}
                                            title={t('tables.workspace.empty')}
                                            description={t('tables.workspace.empty_description')}
                                            actions={canInsert ? [
                                                {
                                                    label: t('tables.workspace.add_row'),
                                                    icon: PlusIcon,
                                                    onClick: () => addRow(),
                                                },
                                                {
                                                    label: t('tables.workspace.with_form'),
                                                    variant: 'outline',
                                                    onClick: () =>
                                                        setPanel({
                                                            type: 'insert',
                                                        }),
                                                },
                                            ] : []}
                                        />
                                    )
                                }
                            />
                        )}

                        <PendingChangesBar
                            projectId={project.id}
                            table={table}
                            rows={gridRows}
                            columns={columns}
                            onApplied={() => setRefreshKey(refreshKey + 1)}
                        />

                        {active && structure && (
                            <SelectionActionBar
                                table={table}
                                driver={project.driver_config?.driver_id ?? ''}
                                rows={gridRows}
                                columns={columns}
                                canEditSavedRows={canEditSavedRows}
                                canDeleteSavedRows={canDeleteSavedRows}
                                canInsert={canInsert}
                                {...selectionActions}
                            />
                        )}

                        <PaginationBar
                            page={view.page}
                            perPage={view.perPage}
                            rowsOnPage={page?.rows.length ?? 0}
                            hasMore={page?.hasMore ?? false}
                            count={count}
                            onPageChange={(next) =>
                                setView({ ...view, page: next })
                            }
                            onPerPageChange={(perPage) =>
                                changeView({ perPage })
                            }
                        />
                    </>
                )}

                {tab === 'structure' &&
                    (structure ? (
                        <StructureView
                            projectId={project.id}
                            structure={structure}
                            onOpenTable={(next) => onOpenTable(next)}
                            onEditStructure={canOnProject('alter', table) ? () => onAction('design', table) : undefined}
                            driver={project.driver_config?.driver_id ?? ''}
                            onColumnMoved={
                                canOnProject('alter', table) &&
                                project.driver_config?.driver_id !== DefaultDrivers.POSTGRESQL
                                    ? () => {
                                          setRefreshKey(refreshKey + 1);
                                          onSchemaChanged();
                                      }
                                    : undefined
                            }
                        />
                    ) : (
                        <Skeleton className="m-4 h-64" />
                    ))}

                {tab === 'definition' && (
                    <DefinitionView projectId={project.id} table={table} />
                )}
            </ResizablePanel>

            {panel && (
                <>
                    <ResizableHandle
                        withHandle
                        className="cursor-col-resize bg-transparent"
                    />
                    <ResizablePanel
                        id="table-side-panel"
                        defaultSize="420px"
                        minSize="340px"
                        maxSize="55%"
                        className="flex min-w-0 flex-col overflow-hidden rounded-lg rounded-t-none border bg-background"
                    >
                        <div className="flex items-center gap-2 border-b py-1.5 pr-1.5 pl-4">
                            <h2
                                className="min-w-0 flex-1 truncate text-sm font-medium"
                                title={panelTitle}
                            >
                                {panelTitle}
                            </h2>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() => setPanel(null)}
                                aria-label={t('tables.workspace.close_panel')}
                            >
                                <XIcon />
                            </Button>
                        </div>

                        {panel.type === 'record' && (
                            <RecordPanel
                                projectId={project.id}
                                location={panel.location}
                            />
                        )}
                        {panel.type === 'insert' && (
                            <InsertRecordPanel
                                key={table}
                                projectId={project.id}
                                table={table}
                                initialValues={panel.values}
                                onInserted={() => setRefreshKey(refreshKey + 1)}
                                onClose={() => setPanel(null)}
                            />
                        )}
                        {panel.type === 'insights' && (
                            <InsightsPanel
                                projectId={project.id}
                                table={table}
                                disabledReason={disabledReason}
                                onAskChat={askChat}
                            />
                        )}
                        {panel.type === 'chat' && (
                            <QuickChatPanel
                                projectId={project.id}
                                table={table}
                                view={view}
                                disabledReason={disabledReason}
                                messages={quickChatMessages}
                                onMessagesChange={setQuickChatMessages}
                                onAskChat={askChat}
                            />
                        )}
                        {panel.type === 'indexes' && (
                            <IndexesPanel
                                projectId={project.id}
                                table={table}
                                view={view}
                                disabledReason={disabledReason}
                                onAskChat={askChat}
                            />
                        )}
                    </ResizablePanel>
                </>
            )}
        </ResizablePanelGroup>
    );
}
