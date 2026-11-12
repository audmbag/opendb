import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon, ChevronLeftIcon, ChevronRightIcon, DownloadIcon, SheetIcon } from 'lucide-react';
import { useState } from 'react';
import ArtifactFrame from '@/components/ai/artifacts/artifact-frame';
import { formatCell, formatNumber, useConversationId } from '@/components/ai/artifacts/format';
import { Badge } from '@/components/reui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import { cn } from '@/lib/utils';
import type { QueryResultsPage, QueryResultsToolUIPart } from '@/types/agent';
import { results } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: QueryResultsToolUIPart;
    enabled: boolean;
};

type Sort = { column: string; direction: 'asc' | 'desc' } | null;

const PER_PAGE = 10;

/**
 * The next sort of a column: ascending, then descending, then the order of the query.
 */
const nextSort = (sort: Sort, column: string): Sort => {
    if (sort?.column !== column) {
        return { column, direction: 'asc' };
    }

    return sort.direction === 'asc' ? { column, direction: 'desc' } : null;
};

export default function QueryResultsPreview({ part, enabled }: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const conversationId = useConversationId();
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState<Sort>(null);

    const routeArgs = { conversation: conversationId, toolCallId: part.toolCallId };
    const sortQuery = sort ? { sort: sort.column, direction: sort.direction } : {};

    const { ref, data, error, processing } = useToolArtifact<QueryResultsPage>(
        results.url(routeArgs, { query: { page, perPage: PER_PAGE, ...sortQuery } }),
        enabled,
    );

    const total = data?.total ?? part.output.totalRows;
    const lastPage = Math.max(1, Math.ceil(total / PER_PAGE));
    const columns = data?.columns.length ? data.columns : part.output.columns;

    const sortBy = (column: string) => {
        setSort(nextSort(sort, column));
        setPage(1);
    };

    return (
        <ArtifactFrame
            ref={ref}
            icon={SheetIcon}
            title={part.output.title ?? t('ai.artifacts.query_results')}
            badge={<Badge>{tChoice('tables.counts.rows', total, { count: formatNumber(total) })}</Badge>}
            data={data}
            error={error}
            processing={processing}
            skeletonClassName="h-64"
            actions={
                <a className={buttonVariants({variant: 'outline', size: 'sm'})} href={results.url(routeArgs, { query: { format: 'csv', ...sortQuery } })} download>
                    <DownloadIcon />
                    CSV
                </a>
            }
        >
            {data && (
                <>
                    <div className="max-h-112 overflow-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="sticky top-0 z-10 bg-muted backdrop-blur-sm">
                                <tr>
                                    {columns.map((column) => (
                                        <th key={column} className="border-b border-border px-3 py-2 font-medium whitespace-nowrap">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1 hover:text-foreground"
                                                onClick={() => sortBy(column)}
                                            >
                                                {column}
                                                {sort?.column !== column && <ArrowUpDownIcon className="size-4 text-muted-foreground" />}
                                                {sort?.column === column && sort.direction === 'asc' && <ArrowUpIcon className="size-4" />}
                                                {sort?.column === column && sort.direction === 'desc' && <ArrowDownIcon className="size-4" />}
                                            </button>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {data.rows.map((row, index) => (
                                    <tr key={index} className="border-b border-border/60 last:border-0 hover:bg-muted/40 even:bg-muted">
                                        {columns.map((column) => (
                                            <td
                                                key={column}
                                                title={formatCell(row[column])}
                                                className={cn('max-w-64 truncate px-3 py-1.5', {
                                                    'text-muted-foreground italic': row[column] === null,
                                                })}
                                            >
                                                {formatCell(row[column])}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {data.rows.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">{t('common.no_results')}</p>
                        )}
                    </div>

                    {lastPage > 1 && (
                        <div className="flex items-center justify-end gap-2 border-t border-border px-3 py-2 text-sm text-muted-foreground">
                            {t('common.page_of', { page, last: lastPage })}
                            <Button variant="ghost" size="icon-sm" disabled={page <= 1} onClick={() => setPage(page - 1)} aria-label={t('common.previous_page')}>
                                <ChevronLeftIcon />
                            </Button>
                            <Button variant="ghost" size="icon-sm" disabled={page >= lastPage} onClick={() => setPage(page + 1)} aria-label={t('common.next_page')}>
                                <ChevronRightIcon />
                            </Button>
                        </div>
                    )}
                </>
            )}
        </ArtifactFrame>
    );
}
