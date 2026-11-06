import type { UIMessage } from 'ai';
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
import { useEffect, useMemo, useRef, useState } from 'react';
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
import { booleanOf, cellRawText } from '@/components/tables/data-grid/format';
import type { GridActions } from '@/components/tables/data-grid/grid-context-menu';
import { useColumnWidths } from '@/components/tables/data-grid/use-column-widths';
import DefinitionView from '@/components/tables/definition-view';
import FilterBar, { createFilter } from '@/components/tables/filter-bar';
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
    Row,
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
import { useTableRowCount, useTableRows } from '@/hooks/use-table-rows';
import { cn } from '@/lib/utils';
import {
    rowKeyOf,
    useTableChangesApi,
    useTableChangesStore,
} from '@/store/use-table-changes-store';
import { useTableGridApi } from '@/store/use-table-grid-store';
import type { CellValue, RecordKey } from '@/types/agent';
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

/**
 * Text longer than this is cut before being sent to the chat: a row is context, not the question.
 */
const CHAT_CONTEXT_LIMIT = 4000;

const AGENT_DISABLED_REASON =
    "L'assistant n'a pas accès à cette table : elle est désactivée dans les paramètres du projet.";

/**
 * A value typed in the grid that equals the one in the database, whatever the way it is written ("36" and 36, true and 1).
 */
const sameCellValue = (
    column: TableColumn,
    value: CellValue,
    original: CellValue,
): boolean => {
    if (column.kind === 'boolean') {
        return booleanOf(value) === booleanOf(original);
    }

    return (
        value === original ||
        (value !== null &&
            original !== null &&
            String(value) === String(original))
    );
};

const keyOf = (row: Row, primaryKey: string[]): RecordKey =>
    Object.fromEntries(
        primaryKey.map((column) => [column, row[column] ?? null]),
    );

const truncated = (text: string) =>
    text.length > CHAT_CONTEXT_LIMIT
        ? `${text.slice(0, CHAT_CONTEXT_LIMIT)}…`
        : text;

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
}: Props) {
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
    const canEditSavedRows = primaryKey.length > 0;
    const { widths, resize } = useColumnWidths(project.id, table, columns);
    const disabledReason = summary.agentAccessEnabled
        ? null
        : AGENT_DISABLED_REASON;

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

    const gridRows = useMemo<GridRow[]>(() => {
        const rowErrors = (prefix: string): Record<string, string> => {
            const located: Record<string, string> = {};

            for (const [key, message] of Object.entries(errors)) {
                if (key === prefix) {
                    located[''] = message;
                } else if (key.startsWith(`${prefix}:`)) {
                    located[key.slice(prefix.length + 1)] = message;
                }
            }

            return located;
        };

        const inserted: GridRow[] = inserts.map((insert) => ({
            id: `insert:${insert.id}`,
            state: 'inserted',
            values: insert.values,
            original: null,
            rowKey: null,
            insertId: insert.id,
            number: null,
            changed: Object.keys(insert.values),
            errors: rowErrors(`insert:${insert.id}`),
        }));

        const saved: GridRow[] = (page?.rows ?? []).map((row, index) => {
            const rowKey = primaryKey.length
                ? rowKeyOf(keyOf(row, primaryKey))
                : null;
            const update = rowKey ? updates[rowKey] : undefined;
            const errorsOfRow = rowKey
                ? {
                      ...rowErrors(`update:${rowKey}`),
                      ...rowErrors(`delete:${rowKey}`),
                  }
                : {};

            return {
                id: rowKey ?? `row:${offset + index}`,
                state: rowKey && deletes[rowKey] ? 'deleted' : 'saved',
                values: update ? { ...row, ...update.values } : row,
                original: row,
                rowKey,
                insertId: null,
                number: offset + index + 1,
                changed: update ? Object.keys(update.values) : [],
                errors: errorsOfRow,
            };
        });

        return [...inserted, ...saved];
    }, [page, inserts, updates, deletes, errors, primaryKey, offset]);

    const setCellValue = (
        row: GridRow,
        column: TableColumn,
        value: CellValue,
    ) => {
        if (row.state === 'inserted' && row.insertId) {
            changes.setInsertValue(row.insertId, column.name, value);
        } else if (row.rowKey && row.original) {
            const original = row.original[column.name] ?? null;

            changes.setValue(
                row.rowKey,
                keyOf(row.original, primaryKey),
                column.name,
                sameCellValue(column, value, original) ? original : value,
                original,
            );
        }
    };

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
                    toast.success('Ligne copiée en JSON', { duration: 1500 }),
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
                    "Cette ligne n'existe pas encore : appliquez les modifications pour l'enregistrer.",
                );

                return;
            }

            if (!canEditSavedRows) {
                toast.info(
                    "Sans clé primaire, une ligne de cette table ne peut pas être désignée pour l'ouvrir.",
                );

                return;
            }

            setPanel({
                type: 'record',
                location: { table, key: keyOf(row.original, primaryKey) },
            });
        },
        duplicateRow: (row) => addRow(copiedValues(row)),
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
                `Dans la table « ${table} », explique-moi cette ligne et ce qui la relie au reste de la base${row.original && canEditSavedRows ? ` (${describeKey(keyOf(row.original, primaryKey))})` : ''} :\n\n${JSON.stringify(row.values, null, 2)}`,
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
                `${rows.length} ligne${rows.length > 1 ? 's' : ''} ajoutée${rows.length > 1 ? 's' : ''} en haut de la grille`,
                {
                    description:
                        'Elles seront enregistrées avec les autres modifications.',
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
                `Dans la table « ${table} », que remarques-tu sur ces ${rows.length} lignes${rows.length > CHAT_ROWS_LIMIT ? ` (les ${CHAT_ROWS_LIMIT} premières)` : ''} ?\n\n${JSON.stringify(
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
            `Parle-moi de la table « ${table} » : ce qu'elle contient, à quoi elle sert et ce qui est notable dans ses données.${filters.length ? `\nJe regarde les lignes où ${filters.join(view.match === 'any' ? ' ou ' : ' et ')}.` : ''}`,
        );
    };

    const panelTitle =
        panel?.type === 'record'
            ? `${panel.location.table}, ${describeKey(panel.location.key)}`
            : panel?.type === 'insights'
              ? 'Analyse de la table'
              : panel?.type === 'insert'
                ? `Nouvelle ligne dans ${table}`
                : panel?.type === 'chat'
                  ? `Chat rapide · ${table}`
                  : 'Index conseillés';

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
                                ? `${count.estimated ? 'environ ' : ''}${formatInteger(count.total)} ligne${count.total > 1 ? 's' : ''}`
                                : ''}
                        </span>
                    </div>

                    <Tabs
                        value={tab}
                        onValueChange={(value) => setTab(value as Tab)}
                        className="self-end"
                    >
                        <TabsList variant="line" className="h-9">
                            <TabsTrigger value="data">Données</TabsTrigger>
                            <TabsTrigger value="structure">
                                Structure
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
                            Analyser
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
                            Index
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
                            tooltip="Poser une question rapide sur cette table, sans ouvrir de conversation"
                        >
                            <MessagesSquareIcon />
                            Chat rapide
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={askAboutTable}
                        >
                            <MessageSquareTextIcon />
                            Demander au chat
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Actions sur la table"
                                    tooltip="Actions sur la table"
                                >
                                    <EllipsisIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuItem
                                    onSelect={() => onAction('design', table)}
                                >
                                    <PencilRulerIcon />
                                    Modifier la structure
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => onAction('rename', table)}
                                >
                                    <TextCursorInputIcon />
                                    Renommer
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() =>
                                        onAction('duplicate', table)
                                    }
                                >
                                    <CopyPlusIcon />
                                    Dupliquer
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => onAction('import', table)}
                                >
                                    <FileUpIcon />
                                    Importer un CSV
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => onAction('export', table)}
                                >
                                    <DownloadIcon />
                                    Exporter en SQL
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => onAction('truncate', table)}
                                >
                                    <EraserIcon />
                                    Vider la table
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => onAction('drop', table)}
                                >
                                    <Trash2Icon />
                                    Supprimer la table
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
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
                                    aria-label="Relire les lignes"
                                    tooltip="Relire les lignes"
                                >
                                    <RefreshCwIcon
                                        className={cn(
                                            rowsState.loading &&
                                                'animate-spin motion-reduce:animate-none',
                                        )}
                                    />
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="sm">
                                            <DownloadIcon />
                                            Exporter
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
                                                        ? 'lignes filtrées'
                                                        : 'toute la table'}
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
                                                        ? 'lignes filtrées'
                                                        : 'toute la table'}
                                                </span>
                                            </a>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <ButtonGroup>
                                    <Button
                                        size="sm"
                                        onClick={() => addRow()}
                                        disabled={!structure}
                                        tooltip="Ajouter une ligne dans la grille, enregistrée avec les autres modifications"
                                    >
                                        <PlusIcon />
                                        Ajouter une ligne
                                    </Button>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                size="icon-sm"
                                                disabled={!structure}
                                                aria-label="Autres façons d'ajouter une ligne"
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
                                                    Dans la grille
                                                    <span className="text-xs text-muted-foreground">
                                                        Enregistrée avec les
                                                        autres modifications
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
                                                    Avec un formulaire
                                                    <span className="text-xs text-muted-foreground">
                                                        Vérifiée et enregistrée
                                                        aussitôt
                                                    </span>
                                                </span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </ButtonGroup>
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
                                Cette table n'a pas de clé primaire : ses lignes
                                ne peuvent pas être désignées, donc ni modifiées
                                ni supprimées. Vous pouvez en ajouter.
                            </p>
                        )}

                        {rowsState.error ? (
                            <div className="flex flex-1 items-center justify-center p-6">
                                <EmptyState
                                    icon={SearchXIcon}
                                    title="Impossible de lire ces lignes"
                                    description={rowsState.error}
                                    actions={[
                                        {
                                            label: 'Retirer les filtres',
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
                                            title="Aucune ligne ne correspond"
                                            description="Les filtres excluent toutes les lignes de la table."
                                            actions={[
                                                {
                                                    label: 'Retirer les filtres',
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
                                            title="Cette table est vide"
                                            description="Ajoutez une première ligne : elle sera enregistrée quand vous appliquerez les modifications."
                                            actions={[
                                                {
                                                    label: 'Ajouter une ligne',
                                                    icon: PlusIcon,
                                                    onClick: () => addRow(),
                                                },
                                                {
                                                    label: 'Avec un formulaire',
                                                    variant: 'outline',
                                                    onClick: () =>
                                                        setPanel({
                                                            type: 'insert',
                                                        }),
                                                },
                                            ]}
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
                            onEditStructure={() => onAction('design', table)}
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
                                aria-label="Fermer le panneau"
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
