import { useLaravelReactI18n } from 'laravel-react-i18n';
import { MessageSquareTextIcon } from 'lucide-react';
import { useState } from 'react';
import Alert from '@/components/alert';
import {
    CodeBlock,
    CodeBlockActions,
    CodeBlockCopyButton,
    CodeBlockHeader,
    CodeBlockTitle,
} from '@/components/code-block';
import Button from '@/components/form/button';
import { Badge } from '@/components/reui/badge';
import { uppercaseSqlKeywords } from '@/lib/sql';
import TableAssistantController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableAssistantController';
import type { IndexSuggestions, TableView } from '../types';
import { viewQuery } from '../view';
import { useTableAssistant } from './use-table-assistant';

type Props = {
    projectId: string;
    table: string;
    view: TableView;
    disabledReason: string | null;
    onAskChat: (question: string) => void;
};

const IMPACTS: Record<
    IndexSuggestions['indexes'][number]['impact'],
    { label: string; variant: 'warning' | 'secondary' | 'outline' }
> = {
    high: { label: 'tables.assistant.impacts.high', variant: 'warning' },
    medium: { label: 'tables.assistant.impacts.medium', variant: 'secondary' },
    low: { label: 'tables.assistant.impacts.low', variant: 'outline' },
};

/**
 * The indexes that would speed up the current view, from the plan of its query. Nothing is created: the statements are to copy.
 */
export default function IndexesPanel({
    projectId,
    table,
    view,
    disabledReason,
    onAskChat,
}: Props) {
    const { t } = useLaravelReactI18n();
    const { ask, result, error, processing, reset } =
        useTableAssistant<IndexSuggestions>();
    const [askedFor, setAskedFor] = useState<string | null>(null);

    if (askedFor !== null && askedFor !== table) {
        setAskedFor(null);
        reset();
    }

    const analyze = () => {
        setAskedFor(table);
        ask(
            TableAssistantController.indexes.url({
                project: projectId,
                table: encodeURIComponent(table),
            }),
            viewQuery(view, { withPage: false }),
        );
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {t('tables.assistant.indexes_description')}
                </p>
                <Button
                    size="sm"
                    variant={result ? 'outline' : 'default'}
                    onClick={analyze}
                    disabled={processing || disabledReason !== null}
                    tooltip={disabledReason ?? undefined}
                    processing={processing}
                >
                    {result ? t('tables.assistant.rerun') : t('tables.assistant.analyze_view')}
                </Button>
            </div>

            {disabledReason && (
                <Alert
                    variant="warning"
                    title={t('tables.assistant.unavailable')}
                    message={disabledReason}
                />
            )}
            {error && (
                <Alert
                    variant="destructive"
                    title={t('tables.assistant.no_answer')}
                    message={error}
                />
            )}

            {result && askedFor === table && (
                <>
                    <p className="text-sm leading-relaxed">{result.note}</p>

                    {result.alerts.map((alert, index) => (
                        <Alert
                            key={index}
                            variant={
                                alert.severity === 'danger'
                                    ? 'destructive'
                                    : 'warning'
                            }
                            title={
                                alert.severity === 'danger'
                                    ? t('tables.assistant.plan_problem')
                                    : t('tables.assistant.plan_warning')
                            }
                            message={alert.message}
                        />
                    ))}

                    {result.indexes.map((index) => (
                        <section
                            key={index.statement}
                            className="flex flex-col gap-2"
                        >
                            <div className="flex items-start justify-between gap-3 text-sm">
                                <p>{index.reason}</p>
                                <Badge
                                    variant={IMPACTS[index.impact].variant}
                                    className="shrink-0"
                                >
                                    {t(IMPACTS[index.impact].label)}
                                </Badge>
                            </div>
                            <CodeBlock
                                code={uppercaseSqlKeywords(index.statement)}
                                language="sql"
                                className="[&_pre]:whitespace-pre-wrap"
                            >
                                <CodeBlockHeader>
                                    <CodeBlockTitle>
                                        {index.columns.join(', ')}
                                    </CodeBlockTitle>
                                    <CodeBlockActions>
                                        <CodeBlockCopyButton />
                                    </CodeBlockActions>
                                </CodeBlockHeader>
                            </CodeBlock>
                        </section>
                    ))}

                    <details className="text-sm text-muted-foreground">
                        <summary className="cursor-pointer select-none hover:text-foreground">
                            {t('query_plan.sql.analyzed_query')}
                        </summary>
                        <code className="mt-2 block font-mono text-xs break-all">
                            {uppercaseSqlKeywords(result.query)}
                        </code>
                    </details>

                    <Button
                        variant="outline"
                        size="sm"
                        className="self-start"
                        onClick={() =>
                            onAskChat(
                                `${t('tables.assistant.ask_indexes')}\n\n${result.query}`,
                            )
                        }
                    >
                        <MessageSquareTextIcon />
                        {t('tables.assistant.dig_in_chat')}
                    </Button>
                </>
            )}
        </div>
    );
}
