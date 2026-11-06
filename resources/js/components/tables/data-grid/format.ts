import type { CellValue } from '@/types/agent';
import type { ColumnKind, TableColumn } from '../types';

export const ROW_HEIGHT = 32;

export const HEADER_HEIGHT = 36;

export const GUTTER_WIDTH = 80;

export const MIN_COLUMN_WIDTH = 64;

export const MAX_COLUMN_WIDTH = 640;

const NUMERIC_KINDS: ColumnKind[] = ['integer', 'decimal'];

/**
 * The width a column starts with, from what it usually holds, before the user resizes it.
 */
export function defaultColumnWidth(column: TableColumn): number {
    // The name stays readable in the header, with room for the key and sort icons.
    const header = Math.min(column.name.length * 8 + 52, 280);

    return Math.max(header, kindWidth(column));
}

function kindWidth(column: TableColumn): number {
    switch (column.kind) {
        case 'boolean':
            return 96;
        case 'integer':
            return column.primary ? 88 : 112;
        case 'decimal':
        case 'date':
        case 'time':
            return 128;
        case 'datetime':
            return 184;
        case 'uuid':
            return 296;
        case 'text':
        case 'json':
            return 280;
        default:
            return Math.min(
                Math.max(MIN_COLUMN_WIDTH, (column.max_length ?? 32) * 7),
                240,
            );
    }
}

export const isNumeric = (column: TableColumn): boolean =>
    NUMERIC_KINDS.includes(column.kind);

/**
 * The text of a cell on one line: JSON is compacted and line breaks are shown as spaces.
 */
export function cellText(value: CellValue | object): string {
    if (value === null) {
        return 'NULL';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    return String(value).replace(/\s*\n\s*/g, ' ');
}

/**
 * The value of a cell as it is copied or edited: the whole text, JSON indented.
 */
export function cellRawText(value: CellValue | object): string {
    if (value === null) {
        return '';
    }

    return typeof value === 'object'
        ? JSON.stringify(value, null, 2)
        : String(value);
}

/**
 * Whether a value of the column is shown as a boolean: SQLite and MySQL keep them as 0 and 1.
 */
export const booleanOf = (value: CellValue): boolean | null =>
    value === null
        ? null
        : value === true || value === 1 || value === '1' || value === 'true';
