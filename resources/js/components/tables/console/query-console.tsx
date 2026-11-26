import type { EditorView } from '@uiw/react-codemirror';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    AlignLeftIcon,
    ChevronDownIcon,
    HistoryIcon,
    LockIcon,
    PlayIcon,
    SparklesIcon,
    SquareIcon,
    SquareTerminalIcon,
    TriangleAlertIcon,
    WandSparklesIcon,
    WorkflowIcon,
} from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import AlertDialog from '@/components/alert-dialog';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Kbd } from '@/components/ui/kbd';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useValueAsRef } from '@/hooks/use-value-as-ref';
import { trackEditorRun } from '@/lib/project-activity';
import {
    formatSql,
    parameterNames,
    riskyStatements,
    statementAt,
    uppercaseSqlKeywords,
} from '@/lib/sql';
import { cn } from '@/lib/utils';
import type { QueryPlan } from '@/types/agent';
import QueryConsoleController from '@/wayfinder/actions/App/Http/Controllers/Database/QueryConsoleController';
import TableAssistantController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableAssistantController';
import type { TableRequestError } from '../api';
import { requestTableData } from '../api';
import AiPromptInput from '../assistant/ai-prompt-input';
import { useTableAssistant } from '../assistant/use-table-assistant';
import SqlCodeBlock from '../sql-code-block';
import type { ConsoleRun, TransactionMode } from '../types';
import ParametersDialog from './parameters-dialog';
import type { ParameterValues } from './parameters-dialog';
import ResultView from './result-view';
import SqlEditor from './sql-editor';
import type { Snippet } from './sql-snippets';

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
     * The snippets offered by the completion, as the editor of the project configures them.
     */
    snippets?: Snippet[];
    onEditorReady?: (view: EditorView) => void;
    editorClassName?: string;
    /**
     * What the SQL editor adds to the console. Without it, the console is the one of the Tables tab: its runs are
     * remembered by the browser, without parameters, transaction modes, plan or review.
     */
    editor?: ConsoleEditor;
};

/**
 * The features of a tab of the SQL editor, each with the address it calls.
 */
export type ConsoleEditor = {
    runUrl: string;
    rowsUrl: string;
    cancelUrl: string;
    exportUrl: string;
    explainUrl: string;
    reviewUrl: string;
    /**
     * The file of the tab: sent with each run for the history, and naming the downloads.
     */
    fileName: string;
    /**
     * Whether the engine can interrupt a query: not SQLite.
     */
    canCancel: boolean;
    /**
     * Only reads run, and their results cannot be changed.
     */
    readOnly: boolean;
    /**
     * Called after a run, successful or not, to read the history again.
     */
    onRan: () => void;
};

const TRANSACTION_MODES: Record<
    TransactionMode,
    { label: string; description: string }
> = {
    auto: {
        label: 'tables.console.modes.auto',
        description: 'tables.console.modes.auto_description',
    },
    atomic: {
        label: 'tables.console.modes.atomic',
        description: 'tables.console.modes.atomic_description',
    },
    dry_run: {
        label: 'tables.console.modes.dry_run',
        description: 'tables.console.modes.dry_run_description',
    },
};

/**
 * The mode and the values of the parameters of a file, kept by the browser for its next runs.
 */
type RunSettings = { mode: TransactionMode; bindings: ParameterValues };

const runSettingsKey = (projectId: string, fileName: string) =>
    `opendb:sql-editor-run:${projectId}:${fileName}`;

function readRunSettings(projectId: string, fileName: string): RunSettings {
    try {
        const stored = JSON.parse(
            localStorage.getItem(runSettingsKey(projectId, fileName)) ?? 'null',
        ) as Partial<RunSettings> | null;

        return {
            mode:
                stored?.mode && stored.mode in TRANSACTION_MODES
                    ? stored.mode
                    : 'auto',
            bindings: stored?.bindings ?? {},
        };
    } catch {
        return { mode: 'auto', bindings: {} };
    }
}

function writeRunSettings(
    projectId: string,
    fileName: string,
    settings: RunSettings,
): void {
    try {
        localStorage.setItem(
            runSettingsKey(projectId, fileName),
            JSON.stringify(settings),
        );
    } catch {
        // Remembered only when the browser allows it.
    }
}

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
    fix: 'tables.console.reviews.fix',
    explain: 'tables.console.reviews.explain',
    optimize: 'tables.console.reviews.optimize',
};

/**
 * The diagram of the plan is heavy: it is loaded the first time a plan is asked for.
 */
const QueryPlanView = lazy(() => import('./query-plan-view'));

const HISTORY_SIZE = 30;
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
    snippets,
    onEditorReady,
    editorClassName,
    editor,
}: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const withLocalHistory = editor === undefined;
    const explainUrl = editor?.explainUrl;
    const reviewUrl = editor?.reviewUrl;
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
    // Kept while the popover is closed: a click aside does not lose what was described.
    const [writerPrompt, setWriterPrompt] = useState('');
    const [writtenRange, setWrittenRange] = useState<{
        from: number;
        to: number;
    } | null>(null);
    const writer = useTableAssistant<{ sql: string; explanation: string }>();
    const [runSettings, setRunSettings] = useState<RunSettings>(() =>
        editor
            ? readRunSettings(projectId, editor.fileName)
            : { mode: 'auto', bindings: {} },
    );
    // The values sent with the run shown, to read its next rows or download it with the same parameters.
    const [runBindings, setRunBindings] = useState<ParameterValues>({});
    const runId = useRef<string | null>(null);
    const [cancelling, setCancelling] = useState(false);
    // A new run gives new results: their pages and changes start again.
    const [runVersion, setRunVersion] = useState(0);
    const lastStatements = useRef<string | null>(null);

    const changeRunSettings = (next: Partial<RunSettings>) => {
        const settings = { ...runSettings, ...next };

        setRunSettings(settings);

        if (editor) {
            writeRunSettings(projectId, editor.fileName, settings);
        }
    };

    /**
     * The values of the `:name` parameters of the statements, asked to the user. Null when they gave up.
     */
    const askParameters = async (
        statements: string,
    ): Promise<ParameterValues | null> => {
        const names = parameterNames(statements);

        if (names.length === 0) {
            return {};
        }

        const values = await ParametersDialog.call({
            names,
            initial: runSettings.bindings,
        });

        if (values) {
            changeRunSettings({
                bindings: { ...runSettings.bindings, ...values },
            });
        }

        return values;
    };

    /**
     * Interrupt the run in progress: it then ends with the error of the database.
     */
    const cancel = async () => {
        if (!editor || !runId.current || cancelling) {
            return;
        }

        setCancelling(true);

        try {
            await requestTableData('post', editor.cancelUrl, {
                data: { runId: runId.current },
            });
            toast.info(t('tables.console.cancelled'), {
                description: t('tables.console.cancelled_description'),
                duration: 2500,
            });
        } catch (exception) {
            toast.error(t('tables.console.cancel_failed'), {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setCancelling(false);
        }
    };

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
        setWriterOpen(false);

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

        const bindings = editor ? await askParameters(selection) : {};

        if (bindings === null) {
            return;
        }

        // An essai à blanc or a read-only project keeps nothing: there is nothing to confirm.
        const risky =
            runSettings.mode === 'dry_run' || editor?.readOnly
                ? []
                : riskyStatements(selection);

        if (
            risky.length > 0 &&
            !(await AlertDialog.call({
                title: t('tables.console.risky_title'),
                description: `${tChoice('tables.console.risky_description', risky.length)}\n\n${risky.map((item) => item.slice(0, 160)).join('\n')}`,
                icon: TriangleAlertIcon,
                variant: 'destructive',
                confirmText: t('tables.console.run'),
                cancelText: t('common.cancel'),
            }))
        ) {
            return;
        }

        setProcessing(true);
        setFailure(null);
        setPlan(null);
        runId.current = editor ? crypto.randomUUID() : null;
        lastStatements.current = selection;
        // Kept until the database answers, even when the page is left meanwhile: leaving the project interrupts it.
        const forgetRun = runId.current ? trackEditorRun(runId.current) : null;

        try {
            const result = await requestTableData<ConsoleRun>(
                'post',
                editor?.runUrl ??
                    QueryConsoleController.url({ project: projectId }),
                {
                    data: editor
                        ? {
                              sql: selection,
                              file: editor.fileName,
                              bindings,
                              mode: runSettings.mode,
                              runId: runId.current,
                          }
                        : { sql: selection },
                },
            );

            setRun(result);
            setRunBindings(bindings);
            setRunVersion((version) => version + 1);
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
            runId.current = null;
            forgetRun?.();
            editor?.onRan();
        }
    };

    const format = () => {
        if (sql.trim() === '') {
            return;
        }

        try {
            setSql(formatSql(sql, driver));
        } catch (exception) {
            toast.error(t('tables.console.format_failed'), {
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

        const bindings = await askParameters(statement.text);

        if (bindings === null) {
            return;
        }

        setExplaining(true);

        try {
            setPlan(
                await requestTableData<QueryPlan>('post', explainUrl, {
                    data: { sql: statement.text, bindings },
                }),
            );
        } catch (exception) {
            toast.error(t('tables.console.explain_failed'), {
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
                {processing && editor?.canCancel ? (
                    <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => void cancel()}
                        processing={cancelling}
                        icon={SquareIcon}
                        tooltip={t('tables.console.cancel_tooltip')}
                    >
                        {t('common.cancel')}
                    </Button>
                ) : (
                    <Button
                        size="sm"
                        onClick={() => void execute()}
                        disabled={processing || sql.trim() === ''}
                        icon={PlayIcon}
                        processing={processing}
                    >
                        {t('tables.console.run')}
                    </Button>
                )}
                {editor &&
                    (editor.readOnly ? (
                        <span
                            className="inline-flex h-7 items-center gap-1.5 rounded-md border px-2 text-xs text-muted-foreground"
                            title={t('tables.console.read_only_description')}
                        >
                            <LockIcon className="size-3.5" />
                            {t('ai.access_modes.read-only')}
                        </span>
                    ) : (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    tooltip={
                                        t(TRANSACTION_MODES[runSettings.mode]
                                            .description)
                                    }
                                >
                                    {t(TRANSACTION_MODES[runSettings.mode].label)}
                                    <ChevronDownIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="w-72">
                                <DropdownMenuLabel>
                                    {t('tables.console.transaction')}
                                </DropdownMenuLabel>
                                <DropdownMenuRadioGroup
                                    value={runSettings.mode}
                                    onValueChange={(value) =>
                                        changeRunSettings({
                                            mode: value as TransactionMode,
                                        })
                                    }
                                >
                                    {(
                                        Object.keys(
                                            TRANSACTION_MODES,
                                        ) as TransactionMode[]
                                    ).map((mode) => (
                                        <DropdownMenuRadioItem
                                            key={mode}
                                            value={mode}
                                            className="flex-col items-start gap-0"
                                        >
                                            {t(TRANSACTION_MODES[mode].label)}
                                            <span className="text-xs text-muted-foreground">
                                                {
                                                    t(TRANSACTION_MODES[mode]
                                                        .description)
                                                }
                                            </span>
                                        </DropdownMenuRadioItem>
                                    ))}
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ))}
                <Button
                    variant="outline"
                    size="sm"
                    onClick={format}
                    disabled={sql.trim() === ''}
                    icon={AlignLeftIcon}
                    tooltip={[
                        t('tables.console.format_tooltip'),
                        [t('tables.console.keys.shift'), 'Alt', 'F'],
                    ]}
                >
                    {t('tables.console.format')}
                </Button>
                <Popover open={writerOpen} onOpenChange={setWriterOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant={writerOpen ? 'secondary' : 'ghost'}
                            size="sm"
                            icon={SparklesIcon}
                            processing={writer.processing}
                            tooltip={
                                writerOpen
                                    ? undefined
                                    : t('tables.console.write_tooltip')
                            }
                        >
                            {t('tables.console.write_with_ai')}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent
                        align="start"
                        className="w-[min(28rem,calc(100vw-2rem))] gap-2 p-2"
                    >
                        <AiPromptInput
                            icon={SparklesIcon}
                            placeholder={t('tables.console.write_placeholder')}
                            label={t('tables.console.write_label')}
                            onSubmit={writeQuery}
                            processing={writer.processing}
                            error={writer.error}
                            prompt={writerPrompt}
                            onPromptChange={setWriterPrompt}
                            maxLength={1000}
                            multiline
                            autoFocus
                        />
                    </PopoverContent>
                </Popover>
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
                                {t('tables.console.review_with_ai')}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-64">
                            <DropdownMenuLabel>
                                {t('tables.console.selection_or_statement')}
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                onSelect={() => void ask('explain')}
                            >
                                {t('tables.console.explain')}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => void ask('optimize')}
                            >
                                {t('tables.console.optimize')}
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
                        tooltip={t('tables.console.plan_tooltip')}
                    >
                        {t('tables.console.plan')}
                    </Button>
                )}
                <span className="hidden text-xs text-muted-foreground sm:inline">
                    <Kbd>Ctrl</Kbd> + <Kbd>{t('shortcuts.keys.enter')}</Kbd>, {t('tables.console.run_hint')}
                    <span className="hidden xl:inline">
                        {' · '}
                        <Kbd>Ctrl</Kbd> + <Kbd>{t('tables.console.keys.shift')}</Kbd> + <Kbd>{t('shortcuts.keys.enter')}</Kbd>,{' '}
                        {t('tables.console.run_statement_hint')}
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
                                {t('sql_editor.explorer.history')}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            className="max-h-96 w-[min(32rem,90vw)] overflow-y-auto"
                        >
                            <DropdownMenuLabel>
                                {t('tables.console.recent_queries')}
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
                                            {t(REVIEW_TITLES[review.mode])}
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
                                            ? t('tables.console.replace_statement')
                                            : t('tables.console.add_to_script')}
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
                                    {t('common.close')}
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
                        snippets={snippets}
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
                            title={t('tables.console.script_failed')}
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
                                                    ? tChoice('tables.counts.rows', item.rows.length, { count: `${item.rows.length}${item.truncated ? '+' : ''}` })
                                                    : tChoice('tables.console.affected', item.affected ?? 0)}
                                            </TabsTrigger>
                                        ))}
                                        {run.error && (
                                            <TabsTrigger
                                                value="error"
                                                disabled
                                                className="text-xs text-destructive disabled:opacity-100"
                                            >
                                                {run.error.index}. {t('tables.console.error')}
                                            </TabsTrigger>
                                        )}
                                    </TabsList>
                                </Tabs>
                            )}

                            {run.error && (
                                <Alert
                                    variant="destructive"
                                    title={t('tables.console.statement_refused', { index: run.error.index })}
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
                                            {t('tables.console.previous_ran')}
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
                                            {t('tables.console.fix_with_ai')}
                                        </Button>
                                    )}
                                </Alert>
                            )}

                            {run.transaction === 'rolled_back' && (
                                <p className="mx-3 mt-2 rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground">
                                    {editor?.readOnly
                                        ? t('tables.console.rolled_back_read_only')
                                        : run.error
                                          ? t('tables.console.rolled_back_error', { index: run.error.index })
                                          : t('tables.console.rolled_back_dry_run')}
                                </p>
                            )}
                            {run.transaction === 'committed' && (
                                <p className="mx-3 mt-2 text-xs text-muted-foreground">
                                    {t('tables.console.committed')}
                                </p>
                            )}

                            {result && !plan && (
                                <ResultView
                                    key={`${runVersion}:${selected}`}
                                    result={result}
                                    context={
                                        editor && {
                                            projectId,
                                            rowsUrl: editor.rowsUrl,
                                            exportUrl: editor.exportUrl,
                                            exportName: editor.fileName,
                                            bindings: runBindings,
                                            editable:
                                                !editor.readOnly &&
                                                run.transaction !==
                                                    'rolled_back',
                                            onChanged: () => {
                                                if (lastStatements.current) {
                                                    void execute(
                                                        lastStatements.current,
                                                    );
                                                }
                                            },
                                        }
                                    }
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
                            title={t('tables.console.title')}
                            description={t('tables.console.empty_description')}
                        />
                    )}
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>
    );
}
