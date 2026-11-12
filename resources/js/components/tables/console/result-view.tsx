import { useVirtualizer } from '@tanstack/react-virtual';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ChartColumnBigIcon,
    ClipboardCopyIcon,
    DownloadIcon,
    MessageSquareTextIcon,
    Table2Icon,
} from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import Button from '@/components/form/button';
import { END_REACHED_THRESHOLD } from '@/components/tables/data-grid/data-grid';
import { truncated } from '@/components/tables/grid-rows';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConversation } from '@/hooks/use-conversation';
import { cn } from '@/lib/utils';
import type { CellValue } from '@/types/agent';
import type { TableRequestError } from '../api';
import { requestTableData } from '../api';
import { cellRawText, cellText } from '../data-grid/format';
import { downloadFile } from '../download';
import type { ConsoleResult } from '../types';
import type { ParameterValues } from './parameters-dialog';
import { COPY_LABELS, copyResult } from './result-copy';
import type { CopyFormat } from './result-copy';

/**
 * What the SQL editor adds to a result: every row of the query, read page by page or downloaded, and cells that can
 * be changed when the rows are the rows of a table.
 */
export type ResultContext = {
    projectId: string;
    rowsUrl: string;
    exportUrl: string;
    /**
     * The name of the file of the tab, which names the downloads.
     */
    exportName: string;
    /**
     * The values of the parameters of the run, to read the next rows of the same query.
     */
    bindings: ParameterValues;
    editable: boolean;
    /**
     * Called once changes made in the result are applied, to run the query again.
     */
    onChanged: () => void;
};

type Props = {
    result: ConsoleResult;
    context?: ResultContext;
};

const RESULT_ROW_HEIGHT = 28;
const RESULT_COLUMN_WIDTH = 180;

/**
 * Beyond this many rows, the browser would slow down: the export downloads the rest.
 */
const MAX_LOADED_ROWS = 100_000;

/**
 * The rows of the result sent to the chat: enough to understand them, the chat can query the rest itself.
 */
const CHAT_ROWS = 50;

/**
 * The diagram and the editable grid are heavy: they are loaded the first time they are shown.
 */
const ResultChart = lazy(() => import('./result-chart'));
const EditableResult = lazy(() => import('./editable-result'));

type View = 'table' | 'chart';

/**
 * A result of the console: the rows of a query, or the number of rows a statement changed. In the SQL editor, the
 * rows beyond the first thousand are loaded as the user scrolls, and the cells can be changed when the rows are the
 * rows of a table.
 */
export default function ResultView({ result, context }: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const [view, setView] = useState<View>('table');
    const [exporting, setExporting] = useState(false);
    const [more, setMore] = useState<CellValue[][]>([]);
    const [loadingMore, setLoadingMore] = useState(false);
    const [exhausted, setExhausted] = useState(!result.truncated);
    const { startConversation } = useConversation();

    if (result.columns === null) {
        return (
            <p className="m-3 text-sm">
                {tChoice('tables.changes.rows_updated', result.affected ?? 0)}
                <span className="text-muted-foreground">
                    {' '}
                    {t('tables.console.in_duration', { duration: result.duration })}
                </span>
            </p>
        );
    }

    const columns = result.columns;
    const rows = more.length === 0 ? result.rows : [...result.rows, ...more];
    const canLoadMore =
        context !== undefined && !exhausted && rows.length < MAX_LOADED_ROWS;

    /**
     * The next page of the query, read again by the server after the rows already shown.
     */
    const loadMore = () => {
        if (!context || !canLoadMore || loadingMore) {
            return;
        }

        setLoadingMore(true);

        requestTableData<{ rows: CellValue[][] }>('post', context.rowsUrl, {
            data: {
                sql: result.statement,
                bindings: context.bindings,
                offset: rows.length,
            },
        })
            .then((page) => {
                setMore((current) => [...current, ...page.rows]);
                setExhausted(page.rows.length < result.rows.length);
            })
            .catch((exception: TableRequestError) => {
                setExhausted(true);
                toast.error(t('tables.console.more_failed'), {
                    description: exception.first(),
                });
            })
            .finally(() => setLoadingMore(false));
    };

    const copy = (format: CopyFormat) =>
        navigator.clipboard
            .writeText(copyResult(format, result.statement, columns, rows))
            .then(() =>
                toast.success(t('tables.console.result_copied', { format: t(COPY_LABELS[format]) }), {
                    duration: 1500,
                }),
            );

    /**
     * Every row of the query, read again by the server.
     */
    const download = async (format: 'csv' | 'json') => {
        if (!context) {
            return;
        }

        setExporting(true);

        try {
            await downloadFile(context.exportUrl, {
                sql: result.statement,
                format,
                name: context.exportName,
                bindings: context.bindings,
            });
        } catch (exception) {
            toast.error(t('tables.console.export_failed'), {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setExporting(false);
        }
    };

    const askChat = () =>
        startConversation({
            text: truncated(
                `${
                    rows.length > CHAT_ROWS
                        ? t('tables.console.ask_result_limited', {
                              limit: CHAT_ROWS,
                              count: `${rows.length}${exhausted ? '' : '+'}`,
                          })
                        : t('tables.console.ask_result')
                }\n\n\`\`\`sql\n${result.statement}\n\`\`\`\n\n${copyResult('markdown', result.statement, columns, rows.slice(0, CHAT_ROWS))}`,
            ),
            files: [],
        });

    const source = context?.editable ? result.source : null;
    const unordered = !exhausted && !/\border\s+by\b/i.test(result.statement);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-1.5 text-xs text-muted-foreground">
                <Tabs
                    value={view}
                    onValueChange={(value) => setView(value as View)}
                >
                    <TabsList className="h-7">
                        <TabsTrigger value="table" className="h-6 text-xs">
                            <Table2Icon />
                            {t('query_plan.tabs.table')}
                        </TabsTrigger>
                        <TabsTrigger value="chart" className="h-6 text-xs">
                            <ChartColumnBigIcon />
                            {t('tables.console.chart')}
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
                <span>
                    {!exhausted
                        ? context
                            ? tChoice('tables.console.rows_loaded', rows.length)
                            : tChoice('tables.console.rows_first', rows.length)
                        : tChoice('tables.counts.rows', rows.length)}{' '}
                    {t('tables.console.in_duration', { duration: result.duration })}
                    {loadingMore && ` · ${t('tables.console.reading')}`}
                </span>
                {unordered && context && (
                    <span title={t('tables.console.unordered_description')}>
                        {t('tables.console.unordered')}
                    </span>
                )}
                {source && (
                    <span className="text-foreground/80">
                        {t('tables.console.editable', { table: source.table })}
                    </span>
                )}
                <div className="ml-auto flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7"
                        icon={MessageSquareTextIcon}
                        disabled={rows.length === 0}
                        onClick={askChat}
                        tooltip={t('tables.console.ask_chat_tooltip')}
                    >
                        {t('tables.console.ask_chat')}
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7"
                                disabled={rows.length === 0}
                            >
                                <ClipboardCopyIcon />
                                {t('common.copy')}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>
                                {t('tables.console.copy_loaded')}
                            </DropdownMenuLabel>
                            {(Object.keys(COPY_LABELS) as CopyFormat[]).map(
                                (format) => (
                                    <DropdownMenuItem
                                        key={format}
                                        onSelect={() => void copy(format)}
                                    >
                                        {t('tables.console.as_format', { format: t(COPY_LABELS[format]) })}
                                    </DropdownMenuItem>
                                ),
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                    {context && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7"
                                    disabled={rows.length === 0}
                                    processing={exporting}
                                >
                                    <DownloadIcon />
                                    {t('tables.workspace.export')}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel>
                                    {t('tables.console.all_rows')}
                                </DropdownMenuLabel>
                                <DropdownMenuItem
                                    onSelect={() => void download('csv')}
                                >
                                    {t('tables.console.as_format', { format: 'CSV' })}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => void download('json')}
                                >
                                    {t('tables.console.as_format', { format: 'JSON' })}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>

            <Suspense
                fallback={
                    <div className="flex flex-col gap-2 p-3" aria-busy="true">
                        <Skeleton className="h-6 w-1/3" />
                        <Skeleton className="h-48 w-full" />
                    </div>
                }
            >
                {view === 'chart' ? (
                    <ResultChart columns={columns} rows={rows} />
                ) : source && context ? (
                    <EditableResult
                        projectId={context.projectId}
                        table={source.table}
                        primaryKey={source.primaryKey}
                        columns={columns}
                        rows={rows}
                        onApplied={context.onChanged}
                        onEndReached={canLoadMore ? loadMore : undefined}
                    />
                ) : (
                    <ResultGrid
                        columns={columns}
                        rows={rows}
                        onEndReached={canLoadMore ? loadMore : undefined}
                    />
                )}
            </Suspense>
        </div>
    );
}

/**
 * The rows of a result, read only, virtualized: only the rows in view are drawn.
 */
function ResultGrid({
    columns,
    rows,
    onEndReached,
}: {
    columns: string[];
    rows: CellValue[][];
    onEndReached?: () => void;
}) {
    'use no memo';

    const scrollRef = useRef<HTMLDivElement>(null);
    const virtualizer = useVirtualizer({
        count: rows.length,
        getScrollElement: () => scrollRef.current,
        estimateSize: () => RESULT_ROW_HEIGHT,
        overscan: 12,
    });
    const items = virtualizer.getVirtualItems();
    const lastShownRow = items.at(-1)?.index ?? -1;

    useEffect(() => {
        if (
            onEndReached &&
            rows.length > 0 &&
            lastShownRow >= rows.length - END_REACHED_THRESHOLD
        ) {
            onEndReached();
        }
    }, [onEndReached, lastShownRow, rows.length]);

    const width = 56 + columns.length * RESULT_COLUMN_WIDTH;

    return (
        <div
            ref={scrollRef}
            className="relative min-h-0 flex-1 overflow-auto border-t text-sm"
        >
            <div
                style={{
                    width,
                    height: virtualizer.getTotalSize() + RESULT_ROW_HEIGHT,
                }}
                className="relative"
            >
                <div
                    className="sticky top-0 z-10 flex border-b bg-muted text-xs font-medium"
                    style={{ height: RESULT_ROW_HEIGHT, width }}
                >
                    <span className="sticky left-0 w-14 shrink-0 border-r bg-muted" />
                    {columns.map((column, index) => (
                        <span
                            key={index}
                            className="flex shrink-0 items-center truncate border-r px-2"
                            style={{ width: RESULT_COLUMN_WIDTH }}
                            title={column}
                        >
                            {column}
                        </span>
                    ))}
                </div>
                {items.map((item) => (
                    <div
                        key={item.key}
                        className="absolute left-0 flex border-b border-border/50 hover:bg-muted/40"
                        style={{
                            top: item.start + RESULT_ROW_HEIGHT,
                            height: RESULT_ROW_HEIGHT,
                            width,
                        }}
                    >
                        <span className="sticky left-0 flex w-14 shrink-0 items-center justify-end border-r bg-background pr-2 text-xs text-muted-foreground tabular-nums">
                            {item.index + 1}
                        </span>
                        {rows[item.index].map((value, index) => (
                            <span
                                key={index}
                                className={cn(
                                    'flex shrink-0 items-center truncate border-r border-border/50 px-2',
                                    value === null &&
                                        'text-xs text-muted-foreground/70',
                                    typeof value === 'number' &&
                                        'justify-end tabular-nums',
                                )}
                                style={{ width: RESULT_COLUMN_WIDTH }}
                                title={
                                    value !== null && String(value).length > 24
                                        ? cellRawText(value).slice(0, 1000)
                                        : undefined
                                }
                            >
                                {cellText(value)}
                            </span>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}
