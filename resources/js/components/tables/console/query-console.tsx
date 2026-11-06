import { useVirtualizer } from '@tanstack/react-virtual';
import type { EditorView } from '@uiw/react-codemirror';
import {
    AlignLeftIcon,
    ClipboardCopyIcon,
    DownloadIcon,
    HistoryIcon,
    PlayIcon,
    SparklesIcon,
    SquareTerminalIcon,
    WandSparklesIcon,
    WorkflowIcon,
} from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Kbd } from '@/components/ui/kbd';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useValueAsRef } from '@/hooks/use-value-as-ref';
import { formatSql, statementAt, uppercaseSqlKeywords } from '@/lib/sql';
import { cn } from '@/lib/utils';
import type { QueryPlan } from '@/types/agent';
import QueryConsoleController from '@/wayfinder/actions/App/Http/Controllers/Database/QueryConsoleController';
import TableAssistantController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableAssistantController';
import type { TableRequestError } from '../api';
import { requestTableData } from '../api';
import AiPromptInput from '../assistant/ai-prompt-input';
import { useTableAssistant } from '../assistant/use-table-assistant';
import { cellRawText, cellText } from '../data-grid/format';
import { downloadFile } from '../download';
import SqlCodeBlock from '../sql-code-block';
import type { ConsoleResult, ConsoleRun } from '../types';
import { COPY_LABELS, copyResult } from './result-copy';
import type { CopyFormat } from './result-copy';
import SqlEditor from './sql-editor';

type Props = {
    projectId: string;
    driver: string;
    /**
     * The names of the tables, offered by the completion of the editor.
     */
    tables: string[];
    initialSql?: string;
    /**
     * Called after statements that may have changed the structure of the database ran.
     */
    onSchemaChanged: () => void;
    /**
     * The script, when the caller owns it, like the editor that saves it to a file. Without it, the console keeps
     * its own script, started from initialSql.
     */
    sql?: string;
    onSqlChange?: (sql: string) => void;
    /**
     * The columns of each table, completed after `table.`.
     */
    completion?: Record<string, string[]>;
    /**
     * Where the statements are sent: the console of the Tables tab by default.
     */
    runUrl?: string;
    /**
     * The extra fields sent with the statements, like the file of the editor they come from.
     */
    runData?: Record<string, unknown>;
    /**
     * Called after a run, successful or not, by the callers that keep their own history.
     */
    onRan?: () => void;
    /**
     * The recent queries remembered by this browser, in a menu. The editor keeps its history elsewhere.
     */
    withLocalHistory?: boolean;
    onEditorReady?: (view: EditorView) => void;
    editorClassName?: string;
    /**
     * Where every row of a SELECT is downloaded, beyond the rows shown, and the name of the file. Without it, a
     * result can only be copied.
     */
    exportUrl?: string;
    exportName?: string;
    /**
     * Where the plan of the statement under the cursor is computed. Without it, the console has no plan.
     */
    explainUrl?: string;
    /**
     * Where the agent fixes, explains or optimizes a statement. Without it, the console only writes with the AI.
     */
    reviewUrl?: string;
};

type ReviewMode = 'fix' | 'explain' | 'optimize';

/**
 * What the agent said of a statement, and where the statement was in the script when it was asked.
 */
type Review = {
    mode: ReviewMode;
    range: { from: number; to: number } | null;
    explanation: string;
    sql: string | null;
};

const REVIEW_TITLES: Record<ReviewMode, string> = {
    fix: 'Correction proposée',
    explain: 'Explication',
    optimize: 'Optimisation proposée',
};

/**
 * The diagram of the plan is heavy: it is loaded the first time a plan is asked for.
 */
const QueryPlanView = lazy(() => import('./query-plan-view'));

const HISTORY_SIZE = 30;
const RESULT_ROW_HEIGHT = 28;
const RESULT_COLUMN_WIDTH = 180;
const SCHEMA_CHANGE = /^\s*(create|alter|drop|rename|truncate)\b/i;

const historyKey = (projectId: string) => `opendb:query-history:${projectId}`;

function readHistory(projectId: string): string[] {
    try {
        return JSON.parse(localStorage.getItem(historyKey(projectId)) ?? '[]');
    } catch {
        return [];
    }
}

function remember(projectId: string, sql: string): string[] {
    const history = [
        sql,
        ...readHistory(projectId).filter((item) => item !== sql),
    ].slice(0, HISTORY_SIZE);

    try {
        localStorage.setItem(historyKey(projectId), JSON.stringify(history));
    } catch {
        // The history is a convenience: without storage, it lasts as long as the page.
    }

    return history;
}

/**
 * A console to run any statement on the database, as in a SQL client: Ctrl+Enter runs the script, or only the
 * selected part of it, written in an editor that highlights and completes the SQL of the database. Each statement gives its result, the first thousand rows of a query.
 */
export default function QueryConsole({
    projectId,
    driver,
    tables,
    initialSql = '',
    onSchemaChanged,
    sql: ownedSql,
    onSqlChange,
    completion,
    runUrl,
    runData,
    onRan,
    withLocalHistory = true,
    onEditorReady,
    editorClassName,
    exportUrl,
    exportName,
    explainUrl,
    reviewUrl,
}: Props) {
    const [localSql, setLocalSql] = useState(initialSql);
    const sql = ownedSql ?? localSql;
    const setSql = (next: string) => {
        setLocalSql(next);
        onSqlChange?.(next);
    };
    const [run, setRun] = useState<ConsoleRun | null>(null);
    const [failure, setFailure] = useState<string | null>(null);
    const [selected, setSelected] = useState(0);
    const [processing, setProcessing] = useState(false);
    const [history, setHistory] = useState(() => readHistory(projectId));
    const selectedText = useRef<string | null>(null);
    const view = useRef<EditorView | null>(null);
    const [plan, setPlan] = useState<QueryPlan | null>(null);
    const [explaining, setExplaining] = useState(false);
    const [review, setReview] = useState<Review | null>(null);
    const [reviewing, setReviewing] = useState<ReviewMode | null>(null);
    const reviewer = useTableAssistant<{
        explanation: string;
        sql: string | null;
    }>();
    // Read when the assistant answers: the user may have typed meanwhile.
    const sqlRef = useValueAsRef(sql);
    const [writerOpen, setWriterOpen] = useState(false);
    const [writtenRange, setWrittenRange] = useState<{
        from: number;
        to: number;
    } | null>(null);
    const writer = useTableAssistant<{ sql: string; explanation: string }>();

    /**
     * The statements the user describes, written by the assistant after the script and selected:
     * Ctrl+Enter then runs them alone.
     */
    const writeQuery = async (prompt: string): Promise<boolean> => {
        const written = await writer.ask(
            TableAssistantController.writeQuery.url({ project: projectId }),
            { prompt, script: sqlRef.current },
        );

        if (!written) {
            return false;
        }

        const comment = written.explanation
            ? `-- ${written.explanation.replace(/\s*\n\s*/g, ' ')}\n`
            : '';
        const script = sqlRef.current.trimEnd();
        const before = script === '' ? '' : `${script}\n\n`;
        const next = `${before}${comment}${uppercaseSqlKeywords(written.sql)}`;

        setSql(next);
        setWrittenRange({ from: before.length, to: next.length });

        return true;
    };

    /**
     * Run the given statements, or else the selected text, or else the whole script.
     */
    const execute = async (statements?: string) => {
        const selection = statements ?? selectedText.current ?? sql;

        if (selection.trim() === '' || processing) {
            return;
        }

        setProcessing(true);
        setFailure(null);
        setPlan(null);

        try {
            const result = await requestTableData<ConsoleRun>(
                'post',
                runUrl ?? QueryConsoleController.url({ project: projectId }),
                { data: { ...runData, sql: selection } },
            );

            setRun(result);
            setSelected(Math.max(0, result.results.length - 1));

            if (withLocalHistory) {
                setHistory(remember(projectId, selection.trim()));
            }

            if (
                result.results.some((item) =>
                    SCHEMA_CHANGE.test(item.statement),
                )
            ) {
                onSchemaChanged();
            }
        } catch (exception) {
            setRun(null);
            setFailure((exception as TableRequestError).first());
        } finally {
            setProcessing(false);
            onRan?.();
        }
    };

    const format = () => {
        if (sql.trim() === '') {
            return;
        }

        try {
            setSql(formatSql(sql, driver));
        } catch (exception) {
            toast.error("Le SQL n'a pas pu être formaté", {
                description: (exception as Error).message.split('\n')[0],
            });
        }
    };

    /**
     * The selected text, or else the statement under the cursor, with where it is in the script.
     */
    const target = (): { text: string; from: number; to: number } | null => {
        const editor = view.current;

        if (!editor) {
            return null;
        }

        const { from, to, head } = editor.state.selection.main;
        const bounds =
            from !== to
                ? { from, to }
                : statementAt(editor.state.doc.toString(), head);

        return bounds
            ? { ...bounds, text: editor.state.sliceDoc(bounds.from, bounds.to) }
            : null;
    };

    const explain = async () => {
        const statement = target();

        if (!explainUrl || !statement || explaining) {
            return;
        }

        setExplaining(true);

        try {
            setPlan(
                await requestTableData<QueryPlan>('post', explainUrl, {
                    data: { sql: statement.text },
                }),
            );
        } catch (exception) {
            toast.error("Le plan d'exécution n'a pas pu être calculé", {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setExplaining(false);
        }
    };

    /**
     * Ask the agent about a statement: the refused one to fix it, or else the one under the cursor.
     */
    const ask = async (mode: ReviewMode) => {
        if (!reviewUrl || reviewer.processing) {
            return;
        }

        let statement: { text: string; from: number; to: number } | null;

        if (mode === 'fix' && run?.error) {
            const from = sql.indexOf(run.error.statement);

            statement = {
                text: run.error.statement,
                from,
                to: from + run.error.statement.length,
            };
        } else {
            statement = target();
        }

        if (!statement) {
            return;
        }

        setReviewing(mode);
        setReview(null);

        const answer = await reviewer.ask(reviewUrl, {
            mode,
            sql: statement.text,
            error: mode === 'fix' ? run?.error?.message : undefined,
        });

        setReviewing(null);

        if (answer) {
            setReview({
                mode,
                range:
                    statement.from >= 0
                        ? { from: statement.from, to: statement.to }
                        : null,
                explanation: answer.explanation,
                sql: answer.sql,
            });
        }
    };

    /**
     * Put the version of the agent in place of the statement it read, or after the script when that statement has
     * changed since, and select it: Ctrl+Enter then runs it alone.
     */
    const applyReview = () => {
        if (!review?.sql) {
            return;
        }

        const written = uppercaseSqlKeywords(review.sql);
        const { range } = review;
        const unchanged =
            range !== null &&
            sqlRef.current.slice(range.from, range.to).trim() !== '' &&
            range.to <= sqlRef.current.length;
        const script = sqlRef.current;

        if (unchanged) {
            // The statement read did not include its delimiter: the one of the agent would double it.
            const replacement = script
                .slice(range.to)
                .trimStart()
                .startsWith(';')
                ? written.replace(/;\s*$/, '')
                : written;
            const next =
                script.slice(0, range.from) +
                replacement +
                script.slice(range.to);

            setSql(next);
            setWrittenRange({
                from: range.from,
                to: range.from + replacement.length,
            });
        } else {
            const before =
                script.trimEnd() === '' ? '' : `${script.trimEnd()}\n\n`;

            setSql(`${before}${written}`);
            setWrittenRange({
                from: before.length,
                to: before.length + written.length,
            });
        }

        setReview(null);
    };

    const result = run?.results[selected];

    // The height of the editor against the results, dragged by the user and kept for every console.
    const { defaultLayout, onLayoutChanged } = useDefaultLayout({
        id: 'query-console-layout',
        storage: localStorage,
        panelIds: ['query-console-editor', 'query-console-results'],
    });

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex items-center gap-2 border-b px-3 py-2">
                <Button
                    size="sm"
                    onClick={() => void execute()}
                    disabled={processing || sql.trim() === ''}
                    icon={PlayIcon}
                    processing={processing}
                >
                    Exécuter
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={format}
                    disabled={sql.trim() === ''}
                    icon={AlignLeftIcon}
                    tooltip={[
                        'Mettre en forme le script, mots-clés en majuscules',
                        ['Maj', 'Alt', 'F'],
                    ]}
                >
                    Formater
                </Button>
                <Button
                    variant={writerOpen ? 'secondary' : 'ghost'}
                    size="sm"
                    onClick={() => setWriterOpen(!writerOpen)}
                    aria-pressed={writerOpen}
                    icon={SparklesIcon}
                    tooltip="Décrire une requête à l'assistant : elle est ajoutée à la fin du script et sélectionnée"
                >
                    Écrire avec l'IA
                </Button>
                {reviewUrl && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                // Disabled until the agent answers, whatever it was asked.
                                disabled={
                                    sql.trim() === '' || reviewing !== null
                                }
                                processing={
                                    reviewing === 'explain' ||
                                    reviewing === 'optimize'
                                }
                                icon={WandSparklesIcon}
                            >
                                Relire avec l'IA
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-64">
                            <DropdownMenuLabel>
                                La sélection, ou l'instruction sous le curseur
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                onSelect={() => void ask('explain')}
                            >
                                Expliquer
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => void ask('optimize')}
                            >
                                Optimiser
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
                {explainUrl && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => void explain()}
                        disabled={sql.trim() === ''}
                        processing={explaining}
                        icon={WorkflowIcon}
                        tooltip="Le plan d'exécution de la sélection, ou de l'instruction sous le curseur, sans l'exécuter"
                    >
                        Plan
                    </Button>
                )}
                <span className="hidden text-xs text-muted-foreground sm:inline">
                    <Kbd>Ctrl</Kbd> + <Kbd>Entrée</Kbd>, la sélection seule si
                    du texte est sélectionné
                    <span className="hidden xl:inline">
                        {' · '}
                        <Kbd>Ctrl</Kbd> + <Kbd>Maj</Kbd> + <Kbd>Entrée</Kbd>,
                        l'instruction sous le curseur
                    </span>
                </span>
                {withLocalHistory && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="ml-auto"
                                disabled={history.length === 0}
                            >
                                <HistoryIcon />
                                Historique
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="max-h-96 w-[min(32rem,90vw)] overflow-y-auto"
                        >
                            <DropdownMenuLabel>
                                Requêtes récentes
                            </DropdownMenuLabel>
                            {history.map((item) => (
                                <DropdownMenuItem
                                    key={item}
                                    onSelect={() => setSql(item)}
                                    className="font-mono text-xs"
                                >
                                    <span className="line-clamp-2 break-all">
                                        {item}
                                    </span>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            <ResizablePanelGroup
                orientation="vertical"
                defaultLayout={defaultLayout}
                onLayoutChanged={onLayoutChanged}
                className="min-h-0 flex-1"
            >
                <ResizablePanel
                    id="query-console-editor"
                    defaultSize="40%"
                    minSize="72px"
                    className="flex min-h-0 flex-col"
                >
                    {writerOpen && (
                        <div className="shrink-0 border-b px-3 py-2">
                            <AiPromptInput
                                icon={SparklesIcon}
                                placeholder="Décrivez la requête, par exemple : les 10 clients qui ont le plus commandé"
                                label="Écrire la requête"
                                onSubmit={writeQuery}
                                processing={writer.processing}
                                error={writer.error}
                                autoFocus
                            />
                        </div>
                    )}

                    {(review || reviewer.error) && (
                        <div className="flex max-h-[60%] shrink-0 flex-col gap-2 overflow-y-auto border-b px-3 py-2 text-sm">
                            {reviewer.error ? (
                                <p className="text-destructive">
                                    {reviewer.error}
                                </p>
                            ) : (
                                review && (
                                    <>
                                        <p className="font-medium">
                                            {REVIEW_TITLES[review.mode]}
                                        </p>
                                        <p className="max-h-40 overflow-y-auto whitespace-pre-wrap text-muted-foreground">
                                            {review.explanation}
                                        </p>
                                        {review.sql && (
                                            <SqlCodeBlock
                                                code={uppercaseSqlKeywords(
                                                    review.sql,
                                                )}
                                                className="max-h-48 overflow-auto"
                                            />
                                        )}
                                    </>
                                )
                            )}
                            <div className="flex gap-2">
                                {review?.sql && (
                                    <Button size="sm" onClick={applyReview}>
                                        {review.range
                                            ? "Remplacer l'instruction"
                                            : 'Ajouter au script'}
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        setReview(null);
                                        reviewer.reset();
                                    }}
                                >
                                    Fermer
                                </Button>
                            </div>
                        </div>
                    )}

                    <SqlEditor
                        value={sql}
                        onChange={setSql}
                        driver={driver}
                        tables={tables}
                        completion={completion}
                        onReady={(created) => {
                            view.current = created;
                            onEditorReady?.(created);
                        }}
                        className={cn(
                            'h-auto min-h-0 flex-1 resize-none border-b-0',
                            editorClassName,
                        )}
                        onSelectionChange={(selection) => {
                            selectedText.current = selection;
                        }}
                        onRun={() => void execute()}
                        onRunStatement={(statement) => void execute(statement)}
                        onFormat={format}
                        selection={writtenRange}
                    />
                </ResizablePanel>
                <ResizableHandle withHandle className="cursor-row-resize" />
                <ResizablePanel
                    id="query-console-results"
                    minSize="72px"
                    className="flex min-h-0 flex-col"
                >
                    {failure && (
                        <Alert
                            variant="destructive"
                            title="Le script n'a pas été exécuté"
                            message={failure}
                            className="m-3 w-auto"
                        />
                    )}

                    {run && (
                        <>
                            {(run.results.length > 1 || run.error) && (
                                <Tabs
                                    value={String(selected)}
                                    onValueChange={(value) =>
                                        setSelected(Number(value))
                                    }
                                    className="shrink-0 overflow-x-auto border-b px-2"
                                >
                                    <TabsList variant="line">
                                        {run.results.map((item, index) => (
                                            <TabsTrigger
                                                key={index}
                                                value={String(index)}
                                                title={uppercaseSqlKeywords(
                                                    item.statement,
                                                )}
                                                className="text-xs"
                                            >
                                                {index + 1}.{' '}
                                                {item.columns
                                                    ? `${item.rows.length}${item.truncated ? '+' : ''} ligne${item.rows.length > 1 ? 's' : ''}`
                                                    : `${item.affected ?? 0} modifiée${(item.affected ?? 0) > 1 ? 's' : ''}`}
                                            </TabsTrigger>
                                        ))}
                                        {run.error && (
                                            <TabsTrigger
                                                value="error"
                                                disabled
                                                className="text-xs text-destructive disabled:opacity-100"
                                            >
                                                {run.error.index}. erreur
                                            </TabsTrigger>
                                        )}
                                    </TabsList>
                                </Tabs>
                            )}

                            {run.error && (
                                <Alert
                                    variant="destructive"
                                    title={`Instruction ${run.error.index} refusée`}
                                    message={run.error.message}
                                    className="m-3 w-auto"
                                >
                                    <code className="line-clamp-3 font-mono text-xs break-all opacity-80">
                                        {uppercaseSqlKeywords(
                                            run.error.statement,
                                        )}
                                    </code>
                                    {run.results.length > 0 && (
                                        <p className="text-xs opacity-80">
                                            Les instructions précédentes ont été
                                            exécutées, les suivantes non.
                                        </p>
                                    )}
                                    {reviewUrl && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="mt-1 w-fit"
                                            icon={WandSparklesIcon}
                                            disabled={reviewing !== null}
                                            processing={reviewing === 'fix'}
                                            onClick={() => void ask('fix')}
                                        >
                                            Corriger avec l'IA
                                        </Button>
                                    )}
                                </Alert>
                            )}

                            {result && !plan && (
                                <ResultView
                                    result={result}
                                    exportUrl={exportUrl}
                                    exportName={exportName}
                                />
                            )}
                        </>
                    )}

                    {plan && (
                        <Suspense
                            fallback={
                                <div
                                    className="flex flex-col gap-2 p-3"
                                    aria-busy="true"
                                >
                                    <Skeleton className="h-6 w-1/3" />
                                    <Skeleton className="h-64 w-full" />
                                </div>
                            }
                        >
                            <QueryPlanView
                                plan={plan}
                                onClose={() => setPlan(null)}
                            />
                        </Suspense>
                    )}

                    {!run && !failure && !plan && (
                        <EmptyState
                            icon={SquareTerminalIcon}
                            title="Console SQL"
                            description="Saisissez une ou plusieurs instructions séparées par « ; ». Les requêtes renvoient leurs mille premières lignes, les autres le nombre de lignes modifiées."
                        />
                    )}
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>
    );
}

function ResultView({
    result,
    exportUrl,
    exportName,
}: {
    result: ConsoleResult;
    exportUrl?: string;
    exportName?: string;
}) {
    'use no memo';

    const [exporting, setExporting] = useState(false);

    const scrollRef = useRef<HTMLDivElement>(null);
    const rows = result.rows;
    const columns = result.columns ?? [];

    const virtualizer = useVirtualizer({
        count: rows.length,
        getScrollElement: () => scrollRef.current,
        estimateSize: () => RESULT_ROW_HEIGHT,
        overscan: 12,
    });

    if (result.columns === null) {
        return (
            <p className="m-3 text-sm">
                {result.affected ?? 0} ligne
                {(result.affected ?? 0) > 1 ? 's' : ''} modifiée
                {(result.affected ?? 0) > 1 ? 's' : ''}
                <span className="text-muted-foreground">
                    {' '}
                    en {result.duration} ms
                </span>
            </p>
        );
    }

    const copy = (format: CopyFormat) =>
        navigator.clipboard
            .writeText(copyResult(format, result.statement, columns, rows))
            .then(() =>
                toast.success(`Résultat copié en ${COPY_LABELS[format]}`, {
                    duration: 1500,
                }),
            );

    /**
     * Every row of the query, read again by the server: the console shows only the first thousand.
     */
    const download = async (format: 'csv' | 'json') => {
        if (!exportUrl) {
            return;
        }

        setExporting(true);

        try {
            await downloadFile(exportUrl, {
                sql: result.statement,
                format,
                name: exportName,
            });
        } catch (exception) {
            toast.error("Le résultat n'a pas été exporté", {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setExporting(false);
        }
    };

    const width = 56 + columns.length * RESULT_COLUMN_WIDTH;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex items-center gap-3 px-3 py-1.5 text-xs text-muted-foreground">
                <span>
                    {rows.length} ligne{rows.length > 1 ? 's' : ''}
                    {result.truncated &&
                        ', les suivantes ne sont pas affichées'}{' '}
                    en {result.duration} ms
                </span>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="ml-auto h-7"
                            disabled={rows.length === 0}
                        >
                            <ClipboardCopyIcon />
                            Copier
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>
                            Copier les lignes affichées
                        </DropdownMenuLabel>
                        {(Object.keys(COPY_LABELS) as CopyFormat[]).map(
                            (format) => (
                                <DropdownMenuItem
                                    key={format}
                                    onSelect={() => void copy(format)}
                                >
                                    En {COPY_LABELS[format]}
                                </DropdownMenuItem>
                            ),
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
                {exportUrl && (
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
                                Exporter
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>
                                Toutes les lignes de la requête
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                onSelect={() => void download('csv')}
                            >
                                En CSV
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => void download('json')}
                            >
                                En JSON
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
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
                    {virtualizer.getVirtualItems().map((item) => (
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
                                        value !== null &&
                                        String(value).length > 24
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
        </div>
    );
}
