import { useVirtualizer } from '@tanstack/react-virtual';
import type { VirtualItem } from '@tanstack/react-virtual';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    ArrowUpRightIcon,
    KeyRoundIcon,
    LinkIcon,
} from 'lucide-react';
import React, { memo, useCallback, useEffect, useRef } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { ContextMenu, ContextMenuTrigger } from '@/components/ui/context-menu';
import { cn } from '@/lib/utils';
import {
    useTableGridApi,
    useTableGridStore,
} from '@/store/use-table-grid-store';
import type { CellValue } from '@/types/agent';
import type { Row, Sort, TableColumn } from '../types';
import CellEditor from './cell-editor';
import type { EditorMove } from './cell-editor';
import {
    booleanOf,
    cellRawText,
    cellText,
    GUTTER_WIDTH,
    HEADER_HEIGHT,
    isNumeric,
    ROW_HEIGHT,
} from './format';
import GridContextMenu from './grid-context-menu';
import type { GridActions } from './grid-context-menu';

export type GridRow = {
    /**
     * Stable across pages and refreshes: the primary key of a saved row, the id of a row waiting to be inserted.
     */
    id: string;
    state: 'saved' | 'inserted' | 'deleted';
    values: Row;
    original: Row | null;
    rowKey: string | null;
    insertId: string | null;
    number: number | null;
    changed: string[];
    /**
     * The errors of the last attempt to apply the changes, by column, with '' for the row itself.
     */
    errors: Record<string, string>;
};

type Props = {
    columns: TableColumn[];
    rows: GridRow[];
    widths: number[];
    sorts: Sort[];
    canEditSavedRows: boolean;
    /**
     * Whether a saved row may be deleted, which needs DELETE rather than UPDATE: like changing it by default.
     */
    canDeleteSavedRows?: boolean;
    loading: boolean;
    empty: React.ReactNode;
    onSort: (column: string, additive: boolean) => void;
    onResize: (column: string, width: number, persist: boolean) => void;
    onEdit: (row: GridRow, column: TableColumn, value: CellValue) => void;
    actions: GridActions;
    /**
     * Called as the last rows come into view, to load the next ones, like the result of a query beyond its first page.
     */
    onEndReached?: () => void;
};

/**
 * How close to the last row the next ones are asked for, so that they arrive before the user reaches the end.
 */
export const END_REACHED_THRESHOLD = 50;

/**
 * Whether the user may change the value of the cell: never a primary key of a saved row, a generated or binary column,
 * a deleted row, or any saved row of a table without primary key.
 */
export function isCellEditable(
    row: GridRow,
    column: TableColumn,
    canEditSavedRows: boolean,
): boolean {
    if (!column.editable || row.state === 'deleted') {
        return false;
    }

    return row.state === 'inserted' || (canEditSavedRows && !column.primary);
}

function HeaderCell({
    column,
    index,
    item,
    sort,
    sortRank,
    onSort,
    onResize,
}: {
    column: TableColumn;
    index: number;
    item: VirtualItem;
    sort: Sort | undefined;
    sortRank: number | null;
    onSort: Props['onSort'];
    onResize: Props['onResize'];
}) {
    const { t } = useLaravelReactI18n();
    const startResize = (event: React.PointerEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.stopPropagation();

        const handle = event.currentTarget;
        const startX = event.clientX;
        const startWidth = item.size;
        let width = startWidth;

        handle.setPointerCapture(event.pointerId);

        const move = (moveEvent: PointerEvent) => {
            width = startWidth + moveEvent.clientX - startX;
            onResize(column.name, width, false);
        };

        const end = () => {
            onResize(column.name, width, true);
            handle.removeEventListener('pointermove', move);
            handle.removeEventListener('pointerup', end);
            handle.removeEventListener('pointercancel', end);
        };

        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', end);
        handle.addEventListener('pointercancel', end);
    };

    const SortIcon = sort?.direction === 'desc' ? ArrowDownIcon : ArrowUpIcon;

    return (
        <div
            role="columnheader"
            aria-colindex={index + 2}
            aria-sort={
                sort
                    ? sort.direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : undefined
            }
            className="group/header absolute top-0 flex h-full border-r border-b bg-muted/60 backdrop-blur-sm"
            style={{ left: item.start, width: item.size }}
        >
            <button
                type="button"
                onClick={(event) => onSort(column.name, event.shiftKey)}
                title={`${column.name}, ${column.db_type}${column.nullable ? `, ${t('tables.grid.nullable')}` : ''}\n${t('tables.grid.sort_hint')}`}
                className={cn(
                    'flex min-w-0 flex-1 items-center gap-1.5 px-2 text-left transition-colors hover:bg-muted',
                    isNumeric(column) && 'flex-row-reverse text-right',
                )}
            >
                {column.primary && (
                    <KeyRoundIcon
                        className="size-3.5 shrink-0 text-warning"
                        aria-label={t('tables.designer.columns.primary_key')}
                    />
                )}
                {!column.primary && column.references && (
                    <LinkIcon
                        className="size-3.5 shrink-0 text-muted-foreground"
                        aria-label={t('tables.list.foreign_key')}
                    />
                )}
                <span className="flex min-w-0 flex-col leading-tight">
                    <span className="truncate text-sm font-medium">
                        {column.name}
                    </span>
                    <span className="truncate font-mono text-[11px] text-muted-foreground">
                        {column.db_type}
                    </span>
                </span>
                {sort && (
                    <span
                        className={cn(
                            'ml-auto flex shrink-0 items-center text-foreground',
                            isNumeric(column) && 'mr-auto ml-0',
                        )}
                    >
                        <SortIcon className="size-3.5" />
                        {sortRank !== null && (
                            <span className="text-[11px] tabular-nums">
                                {sortRank}
                            </span>
                        )}
                    </span>
                )}
            </button>
            <div
                role="separator"
                aria-orientation="vertical"
                aria-label={`Redimensionner ${column.name}`}
                onPointerDown={startResize}
                className="absolute top-0 -right-1 z-10 h-full w-2 cursor-col-resize after:absolute after:inset-y-2 after:left-1/2 after:w-px after:bg-transparent after:transition-colors hover:after:bg-sidebar-primary"
            />
        </div>
    );
}

function CellContent({
    column,
    value,
}: {
    column: TableColumn;
    value: CellValue;
}) {
    if (value === null) {
        return <span className="text-xs text-muted-foreground/70">NULL</span>;
    }

    if (column.kind === 'boolean') {
        const checked = booleanOf(value);

        return (
            <span
                className={cn(
                    'text-xs',
                    checked ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {checked ? 'true' : 'false'}
            </span>
        );
    }

    if (column.kind === 'binary') {
        return (
            <span className="text-xs text-muted-foreground">
                {String(value)}
            </span>
        );
    }

    return (
        <span
            className={cn(
                'truncate',
                column.kind === 'json' && 'font-mono text-xs',
            )}
        >
            {cellText(value)}
        </span>
    );
}

const GridCell = memo(function GridCell({
    row,
    rowIndex,
    column,
    columnIndex,
    item,
    editable,
    onCommit,
    onFollow,
}: {
    row: GridRow;
    rowIndex: number;
    column: TableColumn;
    columnIndex: number;
    item: VirtualItem;
    editable: boolean;
    onFollow: (row: GridRow, column: TableColumn) => void;
    onCommit: (
        row: GridRow,
        column: TableColumn,
        columnIndex: number,
        rowIndex: number,
        value: CellValue,
        move: EditorMove,
    ) => void;
}) {
    const { t } = useLaravelReactI18n();
    // One number per cell, so that moving the selection renders only the cells it leaves and reaches: 0 idle, 1 selected, 2 edited.
    const status = useTableGridStore((state) =>
        state.active?.row === rowIndex && state.active.column === columnIndex
            ? state.editing
                ? 2
                : 1
            : 0,
    );
    const active = status > 0;
    const editing = status === 2;

    const value = row.values[column.name] ?? null;
    // A column left empty in a new row is not sent: the database fills it with its default or the next identifier.
    const unset = row.state === 'inserted' && !(column.name in row.values);
    const error = row.errors[column.name];
    const changed = row.changed.includes(column.name);
    // A saved reference leads to the row it points at; a value still pending may not exist yet.
    const followable =
        column.references !== null && value !== null && row.state === 'saved';

    const gridApi = useTableGridApi();

    return (
        <div
            role="gridcell"
            aria-colindex={columnIndex + 2}
            aria-selected={active}
            aria-readonly={!editable}
            data-row={rowIndex}
            data-column={columnIndex}
            title={
                error ??
                (value !== null &&
                typeof value === 'string' &&
                value.length > 40
                    ? cellRawText(value).slice(0, 1000)
                    : undefined)
            }
            className={cn(
                'absolute top-0 flex h-full items-center overflow-hidden border-r border-b border-border/50 px-2 text-sm whitespace-nowrap',
                isNumeric(column) &&
                    value !== null &&
                    'justify-end tabular-nums',
                changed && 'bg-warning/10 dark:bg-warning/15',
                error && 'bg-destructive/10 ring-1 ring-destructive ring-inset',
                active && 'z-10 ring-2 ring-sidebar-primary ring-inset',
            )}
            style={{ left: item.start, width: item.size }}
        >
            {followable && !editing ? (
                <>
                    <span
                        className={cn(
                            'truncate underline decoration-muted-foreground/40 decoration-dotted underline-offset-4',
                            isNumeric(column) && 'ml-auto',
                        )}
                    >
                        {cellText(value)}
                    </span>
                    <button
                        type="button"
                        tabIndex={-1}
                        onMouseDown={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                        }}
                        onClick={(event) => {
                            event.stopPropagation();
                            onFollow(row, column);
                        }}
                        title={`${t('tables.grid.open_row_of', { table: column.references!.table })} (Ctrl+${t('shortcuts.keys.click')})`}
                        aria-label={t('tables.grid.open_row_of', { table: column.references!.table })}
                        className={cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground',
                            isNumeric(column) ? 'ml-1' : 'ml-auto',
                        )}
                    >
                        <ArrowUpRightIcon className="size-3.5" />
                    </button>
                </>
            ) : unset ? (
                <span
                    className="text-xs text-muted-foreground/70"
                    title={
                        column.default !== null
                            ? t('tables.grid.default_value', { value: column.default })
                            : undefined
                    }
                >
                    {column.auto_increment
                        ? 'auto'
                        : column.default !== null
                          ? t('tables.grid.default')
                          : column.nullable
                            ? 'NULL'
                            : t('tables.grid.to_fill')}
                </span>
            ) : (
                <CellContent column={column} value={value} />
            )}
            {editing && editable && (
                <CellEditor
                    column={column}
                    value={value}
                    initialInput={gridApi.getState().initialInput}
                    width={item.size}
                    onCommit={(next, move) =>
                        onCommit(row, column, columnIndex, rowIndex, next, move)
                    }
                    onCancel={() =>
                        onCommit(
                            row,
                            column,
                            columnIndex,
                            rowIndex,
                            value,
                            null,
                        )
                    }
                />
            )}
            {editing && !editable && (
                <ReadOnlyNotice onDone={gridApi.getState().stopEditing} />
            )}
        </div>
    );
});

/**
 * Typing on a cell that cannot change does nothing: the editor closes at once instead of opening.
 */
function ReadOnlyNotice({ onDone }: { onDone: () => void }) {
    useEffect(() => onDone(), [onDone]);

    return null;
}

function Gutter({
    row,
    onOpen,
    onToggle,
}: {
    row: GridRow;
    onOpen: () => void;
    onToggle: (shiftKey: boolean) => void;
}) {
    const { t } = useLaravelReactI18n();
    const rowError = row.errors[''];
    const selected = useTableGridStore((state) => row.id in state.selected);

    return (
        <div
            className={cn(
                'sticky left-0 z-20 flex h-full shrink-0 items-center border-r border-b border-border/50 bg-background',
                selected && 'bg-muted',
            )}
            style={{ width: GUTTER_WIDTH }}
        >
            <span
                className="flex h-full w-7 shrink-0 items-center justify-center"
                onMouseDown={(event) => event.stopPropagation()}
            >
                <Checkbox
                    checked={selected}
                    onClick={(event) => {
                        event.preventDefault();
                        onToggle(event.shiftKey);
                    }}
                    aria-label={t('tables.grid.select_row', { number: row.number ?? '' })}
                    className="size-3.5"
                />
            </span>
            <button
                type="button"
                onClick={onOpen}
                title={
                    rowError ??
                    (row.state === 'inserted'
                        ? t('tables.grid.added_row')
                        : t('tables.grid.row_detail'))
                }
                className={cn(
                    'flex h-full min-w-0 flex-1 items-center justify-end gap-1.5 pr-2 text-xs text-muted-foreground tabular-nums transition-colors hover:text-foreground',
                    rowError && 'text-destructive',
                )}
            >
                {row.state === 'inserted' && (
                    <span className="font-medium text-success">+</span>
                )}
                {row.state === 'deleted' && (
                    <span className="font-medium text-destructive">−</span>
                )}
                {row.state === 'saved' && row.changed.length > 0 && (
                    <span
                        className="size-1.5 rounded-full bg-warning"
                        aria-label={t('tables.grid.changed_row')}
                    />
                )}
                {row.number ?? ''}
            </button>
        </div>
    );
}

const GridRowView = memo(
    ({
        row,
        rowIndex,
        item,
        columns,
        virtualColumns,
        totalWidth,
        canEditSavedRows,
        onCommit,
        onOpen,
        onToggle,
        onFollow,
    }: {
        row: GridRow;
        rowIndex: number;
        item: VirtualItem;
        columns: TableColumn[];
        virtualColumns: VirtualItem[];
        totalWidth: number;
        canEditSavedRows: boolean;
        onCommit: React.ComponentProps<typeof GridCell>['onCommit'];
        onOpen: (row: GridRow) => void;
        onToggle: (row: GridRow, shiftKey: boolean) => void;
        onFollow: (row: GridRow, column: TableColumn) => void;
    }) => (
        <div
            role="row"
            aria-rowindex={rowIndex + 2}
            className={cn(
                'absolute left-0 flex hover:bg-muted/40',
                row.state === 'inserted' && 'bg-success/5',
                row.state === 'deleted' &&
                    'bg-destructive/5 text-muted-foreground line-through decoration-destructive/60',
            )}
            style={{ top: item.start, height: ROW_HEIGHT, width: totalWidth }}
        >
            <Gutter
                row={row}
                onOpen={() => onOpen(row)}
                onToggle={(shiftKey) => onToggle(row, shiftKey)}
            />
            {virtualColumns.map((columnItem) => (
                <GridCell
                    key={columnItem.key}
                    row={row}
                    rowIndex={rowIndex}
                    column={columns[columnItem.index]}
                    columnIndex={columnItem.index}
                    item={columnItem}
                    editable={isCellEditable(
                        row,
                        columns[columnItem.index],
                        canEditSavedRows,
                    )}
                    onCommit={onCommit}
                    onFollow={onFollow}
                />
            ))}
        </div>
    ),
);

GridRowView.displayName = 'GridRowView';

/**
 * The rows of the table, drawn only where they are visible in both directions so that a page of 500 rows and
 * 80 columns scrolls as smoothly as a page of ten. It is driven by the keyboard like a spreadsheet:
 * arrows and Tab move, Enter or typing edits, Escape cancels, Ctrl+C copies.
 */
export default function DataGrid({
    columns,
    rows,
    widths,
    sorts,
    canEditSavedRows,
    canDeleteSavedRows,
    loading,
    empty,
    onSort,
    onResize,
    onEdit,
    actions,
    onEndReached,
}: Props) {
    // The virtualizers keep their state in a mutable instance that the compiler would memoize away.
    'use no memo';

    const { t } = useLaravelReactI18n();

    const scrollRef = useRef<HTMLDivElement>(null);
    const active = useTableGridStore((state) => state.active);
    const editing = useTableGridStore((state) => state.editing);
    const select = useTableGridStore((state) => state.select);
    const startEditing = useTableGridStore((state) => state.startEditing);
    const stopEditing = useTableGridStore((state) => state.stopEditing);
    const gridApi = useTableGridApi();
    const selectedCount = useTableGridStore(
        (state) => Object.keys(state.selected).length,
    );

    // The virtualizers measure every row again when one of these functions changes: they only change with the data.
    const rowKey = useCallback((index: number) => rows[index].id, [rows]);
    const columnKey = useCallback(
        (index: number) => columns[index].name,
        [columns],
    );
    const columnSize = useCallback((index: number) => widths[index], [widths]);
    const rowSize = useCallback(() => ROW_HEIGHT, []);
    const scrollElement = useCallback(() => scrollRef.current, []);

    const rowVirtualizer = useVirtualizer({
        count: rows.length,
        getScrollElement: scrollElement,
        estimateSize: rowSize,
        overscan: 10,
        paddingStart: HEADER_HEIGHT,
        scrollPaddingStart: HEADER_HEIGHT,
        getItemKey: rowKey,
    });

    const columnVirtualizer = useVirtualizer({
        horizontal: true,
        count: columns.length,
        getScrollElement: scrollElement,
        estimateSize: columnSize,
        overscan: 3,
        paddingStart: GUTTER_WIDTH,
        scrollPaddingStart: GUTTER_WIDTH,
        getItemKey: columnKey,
    });

    useEffect(() => {
        columnVirtualizer.measure();
    }, [widths, columnVirtualizer]);

    // A cell selected from outside the grid, like an error of the list under it, is brought into view.
    useEffect(
        () =>
            gridApi.subscribe((state, previous) => {
                if (state.active && state.active !== previous.active) {
                    rowVirtualizer.scrollToIndex(state.active.row, {
                        align: 'auto',
                    });
                    columnVirtualizer.scrollToIndex(state.active.column, {
                        align: 'auto',
                    });
                }
            }),
        [gridApi, rowVirtualizer, columnVirtualizer],
    );

    const virtualRows = rowVirtualizer.getVirtualItems();
    const virtualColumns = columnVirtualizer.getVirtualItems();
    const totalWidth = columnVirtualizer.getTotalSize();
    const lastShownRow = virtualRows.at(-1)?.index ?? -1;

    useEffect(() => {
        if (
            onEndReached &&
            rows.length > 0 &&
            lastShownRow >= rows.length - END_REACHED_THRESHOLD
        ) {
            onEndReached();
        }
    }, [onEndReached, lastShownRow, rows.length]);

    const focusGrid = () => scrollRef.current?.focus({ preventScroll: true });

    const moveTo = (row: number, column: number) => {
        const next = {
            row: Math.max(0, Math.min(rows.length - 1, row)),
            column: Math.max(0, Math.min(columns.length - 1, column)),
        };

        select(next);
        rowVirtualizer.scrollToIndex(next.row, { align: 'auto' });
        columnVirtualizer.scrollToIndex(next.column, { align: 'auto' });
    };

    const commitNow: React.ComponentProps<typeof GridCell>['onCommit'] = (
        row,
        column,
        columnIndex,
        rowIndex,
        value,
        move,
    ) => {
        stopEditing();

        if (value !== (row.values[column.name] ?? null)) {
            onEdit(row, column, value);
        }

        focusGrid();

        if (move === 'down') {
            moveTo(rowIndex + 1, columnIndex);
        }

        if (move === 'up') {
            moveTo(rowIndex - 1, columnIndex);
        }

        if (move === 'right') {
            moveTo(rowIndex, columnIndex + 1);
        }

        if (move === 'left') {
            moveTo(rowIndex, columnIndex - 1);
        }
    };

    /**
     * Tick or untick a row; with Shift, every row between the last one ticked and this one.
     */
    const toggleRow = (row: GridRow, shiftKey: boolean) => {
        const { anchor, toggleRow: toggle, selectRows } = gridApi.getState();
        const from = anchor ? rows.findIndex((item) => item.id === anchor) : -1;
        const to = rows.findIndex((item) => item.id === row.id);

        if (shiftKey && from >= 0 && to >= 0) {
            selectRows(
                rows
                    .slice(Math.min(from, to), Math.max(from, to) + 1)
                    .map((item) => item.id),
                row.id,
            );
        } else {
            toggle(row.id);
        }
    };

    // The rows are memoized: they receive callbacks that never change, which call the latest version of the grid.
    const latest = useRef({
        commitNow,
        openRow: actions.openRow,
        toggleRow,
        follow: actions.followReference,
    });
    latest.current = {
        commitNow,
        openRow: actions.openRow,
        toggleRow,
        follow: actions.followReference,
    };
    const toggle = useCallback(
        (row: GridRow, shiftKey: boolean) =>
            latest.current.toggleRow(row, shiftKey),
        [],
    );
    const follow = useCallback(
        (row: GridRow, column: TableColumn) =>
            latest.current.follow(row, column),
        [],
    );
    const commit = useCallback<
        React.ComponentProps<typeof GridCell>['onCommit']
    >((...args) => latest.current.commitNow(...args), []);
    const openRow = useCallback(
        (row: GridRow) => latest.current.openRow(row),
        [],
    );

    const cellAt = (
        target: EventTarget,
    ): { row: number; column: number } | null => {
        const cell = (target as HTMLElement).closest<HTMLElement>('[data-row]');

        return cell
            ? {
                  row: Number(cell.dataset.row),
                  column: Number(cell.dataset.column),
              }
            : null;
    };

    const handleMouseDown = (event: React.MouseEvent) => {
        const position = cellAt(event.target);

        if (position && (event.ctrlKey || event.metaKey)) {
            const row = rows[position.row];
            const column = columns[position.column];

            if (
                column.references &&
                row.state === 'saved' &&
                (row.values[column.name] ?? null) !== null
            ) {
                event.preventDefault();
                actions.followReference(row, column);

                return;
            }
        }

        if (
            position &&
            (active?.row !== position.row || active.column !== position.column)
        ) {
            select(position);
        }
    };

    const handleDoubleClick = (event: React.MouseEvent) => {
        const position = cellAt(event.target);

        if (position) {
            select(position);
            startEditing();
        }
    };

    const handleKeyDown = (event: React.KeyboardEvent) => {
        if (editing || rows.length === 0 || columns.length === 0) {
            return;
        }

        const current = active ?? { row: 0, column: 0 };
        const pageRows = Math.max(
            1,
            Math.floor(
                ((scrollRef.current?.clientHeight ?? 0) - HEADER_HEIGHT) /
                    ROW_HEIGHT,
            ) - 1,
        );
        const modifier = event.ctrlKey || event.metaKey;

        const moves: Record<string, [number, number] | undefined> = {
            ArrowDown: [
                modifier ? rows.length - 1 : current.row + 1,
                current.column,
            ],
            ArrowUp: [modifier ? 0 : current.row - 1, current.column],
            ArrowRight: [
                current.row,
                modifier ? columns.length - 1 : current.column + 1,
            ],
            ArrowLeft: [current.row, modifier ? 0 : current.column - 1],
            Tab: [current.row, current.column + (event.shiftKey ? -1 : 1)],
            PageDown: [current.row + pageRows, current.column],
            PageUp: [current.row - pageRows, current.column],
            Home: [modifier ? 0 : current.row, 0],
            End: [modifier ? rows.length - 1 : current.row, columns.length - 1],
        };

        const move = moves[event.key];

        if (move) {
            event.preventDefault();
            moveTo(...move);

            return;
        }

        if (!active) {
            return;
        }

        if (event.key === ' ' && event.shiftKey) {
            event.preventDefault();
            toggleRow(rows[active.row], false);
        } else if (event.key === 'Enter' || event.key === 'F2') {
            event.preventDefault();
            startEditing();
        } else if (event.key === 'Escape') {
            select(null);
        } else if (modifier && event.key.toLowerCase() === 'c') {
            event.preventDefault();
            actions.copyValue(rows[active.row], columns[active.column]);
        } else if (event.key.length === 1 && !modifier && !event.altKey) {
            event.preventDefault();
            startEditing(event.key);
        }
    };

    const activeRow = active ? rows[active.row] : undefined;
    const activeColumn = active ? columns[active.column] : undefined;

    return (
        <ContextMenu modal={false}>
            <div className="relative flex min-h-0 flex-1 flex-col">
                <ContextMenuTrigger asChild>
                    <div
                        ref={scrollRef}
                        role="grid"
                        aria-rowcount={rows.length + 1}
                        aria-colcount={columns.length + 1}
                        aria-busy={loading}
                        tabIndex={0}
                        onKeyDown={handleKeyDown}
                        onMouseDown={handleMouseDown}
                        onDoubleClick={handleDoubleClick}
                        onContextMenu={(event) => {
                            const position = cellAt(event.target);

                            if (position) {
                                select(position);
                            } else {
                                event.preventDefault();
                            }
                        }}
                        className="relative min-h-0 flex-1 overflow-auto overscroll-contain [contain:layout_paint] outline-none select-none"
                    >
                        <div
                            className="relative"
                            style={{
                                width: totalWidth,
                                height: rowVirtualizer.getTotalSize(),
                            }}
                        >
                            <div
                                role="row"
                                aria-rowindex={1}
                                className="sticky top-0 z-30 flex"
                                style={{
                                    height: HEADER_HEIGHT,
                                    width: totalWidth,
                                }}
                            >
                                <div
                                    className="sticky left-0 z-10 flex h-full shrink-0 items-center border-r border-b bg-muted"
                                    style={{ width: GUTTER_WIDTH }}
                                >
                                    <span className="flex w-7 justify-center">
                                        <Checkbox
                                            checked={
                                                selectedCount === 0
                                                    ? false
                                                    : selectedCount >=
                                                        rows.length
                                                      ? true
                                                      : 'indeterminate'
                                            }
                                            disabled={rows.length === 0}
                                            onClick={(event) => {
                                                event.preventDefault();

                                                if (selectedCount > 0) {
                                                    gridApi
                                                        .getState()
                                                        .clearSelection();
                                                } else {
                                                    gridApi
                                                        .getState()
                                                        .selectRows(
                                                            rows.map(
                                                                (row) => row.id,
                                                            ),
                                                        );
                                                }
                                            }}
                                            aria-label={
                                                selectedCount > 0
                                                    ? t('tables.grid.unselect_rows')
                                                    : t('tables.grid.select_page')
                                            }
                                            className="size-3.5"
                                        />
                                    </span>
                                </div>
                                {virtualColumns.map((item) => {
                                    const column = columns[item.index];
                                    const sortIndex = sorts.findIndex(
                                        (sort) => sort.column === column.name,
                                    );

                                    return (
                                        <HeaderCell
                                            key={item.key}
                                            column={column}
                                            index={item.index}
                                            item={item}
                                            sort={sorts[sortIndex]}
                                            sortRank={
                                                sorts.length > 1 &&
                                                sortIndex >= 0
                                                    ? sortIndex + 1
                                                    : null
                                            }
                                            onSort={onSort}
                                            onResize={onResize}
                                        />
                                    );
                                })}
                            </div>

                            {virtualRows.map((item) => (
                                <GridRowView
                                    key={item.key}
                                    row={rows[item.index]}
                                    rowIndex={item.index}
                                    item={item}
                                    columns={columns}
                                    virtualColumns={virtualColumns}
                                    totalWidth={totalWidth}
                                    canEditSavedRows={canEditSavedRows}
                                    onCommit={commit}
                                    onOpen={openRow}
                                    onToggle={toggle}
                                    onFollow={follow}
                                />
                            ))}
                        </div>
                    </div>
                </ContextMenuTrigger>

                {loading && (
                    <div
                        className="pointer-events-none absolute inset-x-0 z-40 h-0.5 overflow-hidden"
                        style={{ top: HEADER_HEIGHT - 2 }}
                        aria-hidden
                    >
                        <div className="h-full w-1/3 animate-[table-loading_1.2s_ease-in-out_infinite] bg-sidebar-primary/70 motion-reduce:animate-none" />
                    </div>
                )}

                {rows.length === 0 && !loading && (
                    <div
                        className="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center p-10"
                        style={{ top: HEADER_HEIGHT }}
                    >
                        <div className="pointer-events-auto">{empty}</div>
                    </div>
                )}
            </div>

            {activeRow && activeColumn && (
                <GridContextMenu
                    row={activeRow}
                    column={activeColumn}
                    editable={isCellEditable(
                        activeRow,
                        activeColumn,
                        canEditSavedRows,
                    )}
                    canEditSavedRows={canEditSavedRows}
                    canDeleteSavedRows={canDeleteSavedRows}
                    actions={actions}
                    onEdit={() => {
                        startEditing();
                    }}
                />
            )}
        </ContextMenu>
    );
}
