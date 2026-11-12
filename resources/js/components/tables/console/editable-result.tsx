import { router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { describeKey } from '@/components/ai/artifacts/record/format';
import DataGrid from '@/components/tables/data-grid/data-grid';
import type { GridRow } from '@/components/tables/data-grid/data-grid';
import { cellRawText } from '@/components/tables/data-grid/format';
import type { GridActions } from '@/components/tables/data-grid/grid-context-menu';
import { useColumnWidths } from '@/components/tables/data-grid/use-column-widths';
import {
    buildGridRows,
    editCell,
    keyOf,
    truncated,
} from '@/components/tables/grid-rows';
import PendingChangesBar from '@/components/tables/pending-changes-bar';
import type { Filter, Row, TableColumn } from '@/components/tables/types';
import { useTableStructure } from '@/components/tables/use-table-structure';
import { DEFAULT_VIEW, newFilterId, viewQuery } from '@/components/tables/view';
import { Skeleton } from '@/components/ui/skeleton';
import { useConversation } from '@/hooks/use-conversation';
import {
    createTableChangesStore,
    TableChangesContext,
    useTableChangesApi,
    useTableChangesStore,
} from '@/store/use-table-changes-store';
import {
    createTableGridStore,
    TableGridContext,
} from '@/store/use-table-grid-store';
import type { CellValue } from '@/types/agent';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';

type Props = {
    projectId: string;
    /**
     * The table whose rows the result shows as they are, and its primary key, which the result holds.
     */
    table: string;
    primaryKey: string[];
    columns: string[];
    rows: CellValue[][];
    /**
     * Called once the changes are applied, to run the query again.
     */
    onApplied: () => void;
    onEndReached?: () => void;
};

/**
 * A result of the SQL editor whose cells can be changed as in the Tables tab: the same grid, the same changes waiting
 * to be applied and the same bar to review and apply them. The rows are the ones the query returned.
 */
export default function EditableResult(props: Props) {
    // The changes and the selection belong to this result: a new result starts without any.
    const [stores] = useState(() => ({
        changes: createTableChangesStore(props.table),
        grid: createTableGridStore(),
    }));

    return (
        <TableChangesContext.Provider value={stores.changes}>
            <TableGridContext.Provider value={stores.grid}>
                <EditableGrid {...props} />
            </TableGridContext.Provider>
        </TableChangesContext.Provider>
    );
}

function EditableGrid({
    projectId,
    table,
    primaryKey,
    columns: resultColumns,
    rows: resultRows,
    onApplied,
    onEndReached,
}: Props) {
    const { t } = useLaravelReactI18n();
    const updates = useTableChangesStore((state) => state.updates);
    const inserts = useTableChangesStore((state) => state.inserts);
    const deletes = useTableChangesStore((state) => state.deletes);
    const errors = useTableChangesStore((state) => state.errors);
    const changes = useTableChangesApi().getState();
    const structure = useTableStructure(projectId, table, null, 0);
    const { startConversation } = useConversation();

    // The columns of the table, in the order of the result.
    const columns = useMemo<TableColumn[]>(
        () =>
            structure
                ? resultColumns.flatMap(
                      (name) =>
                          structure.columns.find(
                              (column) => column.name === name,
                          ) ?? [],
                  )
                : [],
        [structure, resultColumns],
    );
    const { widths, resize } = useColumnWidths(projectId, table, columns);

    const gridRows = useMemo<GridRow[]>(
        () =>
            buildGridRows(
                resultRows.map(
                    (row): Row =>
                        Object.fromEntries(
                            resultColumns.map((column, index) => [
                                column,
                                row[index],
                            ]),
                        ),
                ),
                0,
                primaryKey,
                { inserts, updates, deletes, errors },
            ),
        [
            resultRows,
            resultColumns,
            primaryKey,
            inserts,
            updates,
            deletes,
            errors,
        ],
    );

    /**
     * Open a table in the Tables tab, on the rows a filter keeps.
     */
    const openInTables = (target: string, filters: Filter[]) =>
        router.visit(
            TableBrowserController.index.url(
                { project: projectId, table: encodeURIComponent(target) },
                { query: viewQuery({ ...DEFAULT_VIEW, filters }) },
            ),
        );

    const filtersOf = (key: Record<string, CellValue>): Filter[] =>
        Object.entries(key).map(([column, value]) => ({
            id: newFilterId(),
            column,
            operator: value === null ? 'is_null' : '=',
            value: value === null ? null : cellRawText(value),
        }));

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
        setValue: (row, column, value) =>
            editCell(changes, primaryKey, row, column, value),
        openRow: (row) =>
            row.original &&
            openInTables(table, filtersOf(keyOf(row.original, primaryKey))),
        toggleDelete: (row) => {
            if (row.rowKey && row.original) {
                changes.toggleDelete(
                    row.rowKey,
                    keyOf(row.original, primaryKey),
                );
            }
        },
        revertRow: (row) => row.rowKey && changes.revertRow(row.rowKey),
        askChat: (row) =>
            startConversation({
                text: truncated(
                    row.original
                        ? t('tables.workspace.ask_row_with_key', { table, key: describeKey(keyOf(row.original, primaryKey)), values: JSON.stringify(row.values, null, 2) })
                        : t('tables.workspace.ask_row', { table, values: JSON.stringify(row.values, null, 2) }),
                ),
                files: [],
            }),
        followReference: (row, column) => {
            const value = row.values[column.name] ?? null;

            if (column.references && value !== null) {
                openInTables(
                    column.references.table,
                    filtersOf({ [column.references.column]: value }),
                );
            }
        },
    };

    if (!structure) {
        return (
            <div className="flex flex-1 flex-col gap-px p-3" aria-busy="true">
                {Array.from({ length: 8 }, (_, index) => (
                    <Skeleton key={index} className="h-7" />
                ))}
            </div>
        );
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <DataGrid
                columns={columns}
                rows={gridRows}
                widths={widths}
                sorts={[]}
                canEditSavedRows
                loading={false}
                onSort={() => null}
                onResize={resize}
                onEdit={actions.setValue}
                actions={actions}
                empty={
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        {t('tables.console.no_rows')}
                    </p>
                }
                onEndReached={onEndReached}
            />
            <PendingChangesBar
                projectId={projectId}
                table={table}
                rows={gridRows}
                columns={columns}
                onApplied={onApplied}
            />
        </div>
    );
}
