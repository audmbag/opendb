import { useEffect, useState } from 'react';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';
import { requestTableData } from './api';
import type { TableStructure } from './types';

type Fetched = { table: string; version: number; structure: TableStructure };

/**
 * The structure of the table of a tab: the one the page was opened with, or read when the tab opens, and read
 * again whenever the structure of the database changes. The previous structure stays shown while it is read again.
 *
 * @param initial the structure sent with the page, undefined while the page is still loading it
 */
export function useTableStructure(
    projectId: string,
    table: string,
    initial: TableStructure | null | undefined,
    schemaVersion: number,
): TableStructure | null {
    const [fetched, setFetched] = useState<Fetched | null>(null);

    // The structure sent with the page describes the database as it was before any change made from the page.
    const fromPage =
        initial?.name === table && schemaVersion === 0 ? initial : null;
    const upToDate =
        fetched?.table === table && fetched.version === schemaVersion;
    const waitingForPage = initial === undefined && schemaVersion === 0;

    useEffect(() => {
        if (upToDate || fromPage || waitingForPage) {
            return;
        }

        const controller = new AbortController();

        requestTableData<TableStructure>(
            'get',
            TableBrowserController.structure.url({
                project: projectId,
                table: encodeURIComponent(table),
            }),
            { signal: controller.signal },
        )
            .then((structure) =>
                setFetched({ table, version: schemaVersion, structure }),
            )
            .catch(() => null);

        return () => controller.abort();
    }, [projectId, table, schemaVersion, upToDate, fromPage, waitingForPage]);

    if (upToDate) {
        return fetched.structure;
    }

    return fromPage ?? (fetched?.table === table ? fetched.structure : null);
}
