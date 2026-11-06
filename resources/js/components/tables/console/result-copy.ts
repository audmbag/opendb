import type { CellValue } from '@/types/agent';
import { cellRawText } from '../data-grid/format';

export type CopyFormat = 'csv' | 'markdown' | 'insert';

export const COPY_LABELS: Record<CopyFormat, string> = {
    csv: 'CSV',
    markdown: 'tableau Markdown',
    insert: 'INSERT',
};

/**
 * The rows of a result as text to paste elsewhere: CSV, a Markdown table, or the INSERT statements that would add them
 * to the table the query reads.
 */
export function copyResult(
    format: CopyFormat,
    statement: string,
    columns: string[],
    rows: CellValue[][],
): string {
    switch (format) {
        case 'csv': {
            const cell = (value: CellValue) => {
                const text = cellRawText(value);

                return /[",\n\r]/.test(text)
                    ? `"${text.replace(/"/g, '""')}"`
                    : text;
            };

            return [
                columns.map(cell).join(','),
                ...rows.map((row) => row.map(cell).join(',')),
            ].join('\n');
        }
        case 'markdown': {
            const cell = (value: CellValue) =>
                (value === null ? 'NULL' : cellRawText(value))
                    .replace(/\|/g, '\\|')
                    .replace(/\r?\n/g, ' ');

            return [
                `| ${columns.map(cell).join(' | ')} |`,
                `| ${columns.map(() => '---').join(' | ')} |`,
                ...rows.map((row) => `| ${row.map(cell).join(' | ')} |`),
            ].join('\n');
        }
        case 'insert': {
            const table =
                statement.match(/\bfrom\s+([\w."`[\]]+)/i)?.[1] ?? 'resultat';
            const literal = (value: CellValue) =>
                value === null
                    ? 'NULL'
                    : typeof value === 'number'
                      ? String(value)
                      : typeof value === 'boolean'
                        ? value
                            ? 'TRUE'
                            : 'FALSE'
                        : `'${cellRawText(value).replace(/'/g, "''")}'`;

            return rows
                .map(
                    (row) =>
                        `INSERT INTO ${table} (${columns.join(', ')}) VALUES (${row.map(literal).join(', ')});`,
                )
                .join('\n');
        }
    }
}
