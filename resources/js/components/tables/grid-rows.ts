import type { GridRow } from '@/components/tables/data-grid/data-grid';
import { booleanOf } from '@/components/tables/data-grid/format';
import type { Row, TableColumn } from '@/components/tables/types';
import { rowKeyOf } from '@/store/use-table-changes-store';
import type { TableChangesState } from '@/store/use-table-changes-store';
import type { CellValue, RecordKey } from '@/types/agent';

/**
 * Text longer than this is cut before being sent to the chat: a row or a result is context, not the question.
 */
export const CHAT_CONTEXT_LIMIT = 4000;

export const truncated = (text: string) =>
    text.length > CHAT_CONTEXT_LIMIT
        ? `${text.slice(0, CHAT_CONTEXT_LIMIT)}…`
        : text;

/**
 * A value typed in the grid that equals the one in the database, whatever the way it is written ("36" and 36, true and 1).
 */
export const sameCellValue = (
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

export const keyOf = (row: Row, primaryKey: string[]): RecordKey =>
    Object.fromEntries(
        primaryKey.map((column) => [column, row[column] ?? null]),
    );

/**
 * The rows of the grid: the rows waiting to be inserted, then the saved rows with the changes waiting to be applied
 * and the errors of the last attempt, each saved row known by its primary key.
 */
export function buildGridRows(
    rows: Row[],
    offset: number,
    primaryKey: string[],
    {
        inserts,
        updates,
        deletes,
        errors,
    }: Pick<TableChangesState, 'inserts' | 'updates' | 'deletes' | 'errors'>,
): GridRow[] {
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

    const saved: GridRow[] = rows.map((row, index) => {
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
}

/**
 * Keep the value typed in a cell as a change waiting to be applied, or forget the change when the value is back to
 * the one in the database.
 */
export function editCell(
    changes: TableChangesState,
    primaryKey: string[],
    row: GridRow,
    column: TableColumn,
    value: CellValue,
): void {
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
}
