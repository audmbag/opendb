import { useCallback, useMemo, useState } from 'react';
import type { TableColumn } from '../types';
import {
    defaultColumnWidth,
    MAX_COLUMN_WIDTH,
    MIN_COLUMN_WIDTH,
} from './format';

const storageKey = (projectId: string, table: string) =>
    `opendb:table-widths:${projectId}:${table}`;

function readWidths(key: string): Record<string, number> {
    try {
        return JSON.parse(localStorage.getItem(key) ?? '{}') as Record<
            string,
            number
        >;
    } catch {
        return {};
    }
}

/**
 * The width of each column, as the user last resized it in this table. They are kept in the browser only.
 */
export function useColumnWidths(
    projectId: string,
    table: string,
    columns: TableColumn[],
) {
    const key = storageKey(projectId, table);
    const [resized, setResized] = useState<{
        key: string;
        widths: Record<string, number>;
    }>(() => ({ key, widths: readWidths(key) }));

    // Another table was opened: its own widths replace those of the previous one.
    const stored = resized.key === key ? resized.widths : readWidths(key);

    if (resized.key !== key) {
        setResized({ key, widths: stored });
    }

    const widths = useMemo(
        () =>
            columns.map(
                (column) => stored[column.name] ?? defaultColumnWidth(column),
            ),
        [columns, stored],
    );

    const resize = useCallback(
        (column: string, width: number, persist: boolean) => {
            setResized((current) => {
                const next = {
                    ...current.widths,
                    [column]: Math.round(
                        Math.min(
                            MAX_COLUMN_WIDTH,
                            Math.max(MIN_COLUMN_WIDTH, width),
                        ),
                    ),
                };

                if (persist) {
                    try {
                        localStorage.setItem(key, JSON.stringify(next));
                    } catch {
                        // Private browsing or a full storage: the width lasts until the page is left.
                    }
                }

                return { key: current.key, widths: next };
            });
        },
        [key],
    );

    return { widths, resize };
}
