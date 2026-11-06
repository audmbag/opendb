import { useEffect, useRef, useState } from 'react';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import type { RowCount, RowsPage, TableView } from '@/components/tables/types';
import { viewQuery } from '@/components/tables/view';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';

/**
 * Pages already read, kept while the user goes back and forth between them. A new page or a refresh replaces them.
 */
const CACHED_PAGES = 12;

type RowsResult = {
    url: string;
    refreshKey: number;
    page: RowsPage | null;
    error: string | null;
};

/**
 * The page of rows of the view. The previous page stays displayed while the next one loads, a request that is no longer
 * needed is cancelled, and the following page is read in advance so that moving to it is instant.
 */
export function useTableRows(
    projectId: string,
    table: string,
    view: TableView,
    refreshKey: number,
) {
    const [result, setResult] = useState<RowsResult | null>(null);
    const cache = useRef(new Map<string, RowsPage>());
    const lastRefreshKey = useRef(refreshKey);

    const urlOf = (target: TableView) =>
        TableBrowserController.rows.url(
            { project: projectId, table: encodeURIComponent(table) },
            { query: viewQuery(target) },
        );
    const url = urlOf(view);
    const nextUrl = urlOf({ ...view, page: view.page + 1 });

    useEffect(() => {
        const controller = new AbortController();

        if (lastRefreshKey.current !== refreshKey) {
            lastRefreshKey.current = refreshKey;
            cache.current.clear();
        }

        const remember = (key: string, page: RowsPage) => {
            cache.current.delete(key);
            cache.current.set(key, page);

            if (cache.current.size > CACHED_PAGES) {
                cache.current.delete(cache.current.keys().next().value!);
            }
        };

        const cached = cache.current.get(url);

        (cached
            ? Promise.resolve(cached)
            : requestTableData<RowsPage>('get', url, {
                  signal: controller.signal,
              })
        )
            .then((page) => {
                remember(url, page);
                setResult({ url, refreshKey, page, error: null });

                if (page.hasMore && !cache.current.has(nextUrl)) {
                    return requestTableData<RowsPage>('get', nextUrl, {
                        signal: controller.signal,
                    })
                        .then((next) => remember(nextUrl, next))
                        .catch(() => null);
                }
            })
            .catch((error: TableRequestError) => {
                if (!error.cancelled) {
                    setResult({
                        url,
                        refreshKey,
                        page: null,
                        error: error.first(),
                    });
                }
            });

        return () => controller.abort();
    }, [url, nextUrl, refreshKey]);

    return {
        page: result?.page ?? null,
        error: result?.url === url ? result.error : null,
        loading: result?.url !== url || result.refreshKey !== refreshKey,
    };
}

/**
 * The number of rows of the view, read apart from the rows: counting a large table is slow and must not delay them.
 */
export function useTableRowCount(
    projectId: string,
    table: string,
    view: TableView,
    refreshKey: number,
) {
    const [count, setCount] = useState<{ url: string; value: RowCount } | null>(
        null,
    );
    const url = TableBrowserController.count.url(
        { project: projectId, table: encodeURIComponent(table) },
        { query: viewQuery(view, { withPage: false, withSorts: false }) },
    );

    useEffect(() => {
        const controller = new AbortController();

        requestTableData<RowCount>('get', url, { signal: controller.signal })
            .then((value) => setCount({ url, value }))
            .catch(() => null);

        return () => controller.abort();
    }, [url, refreshKey]);

    return count?.url === url ? count.value : null;
}
