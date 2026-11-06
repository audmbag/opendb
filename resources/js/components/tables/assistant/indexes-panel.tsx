import { MessageSquareTextIcon } from 'lucide-react';
import { useState } from 'react';
import {
    CodeBlock,
    CodeBlockActions,
    CodeBlockCopyButton,
    CodeBlockHeader,
    CodeBlockTitle,
} from '@/components/code-block';
import Alert from '@/components/alert';
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
    high: { label: 'Gain important', variant: 'warning' },
    medium: { label: 'Gain moyen', variant: 'secondary' },
    low: { label: 'Gain faible', variant: 'outline' },
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
                    L'assistant lit le plan d'exécution de la vue actuelle, avec
                    ses filtres et son tri, et propose les index qui
                    l'accéléreraient. Aucun index n'est créé.
                </p>
                <Button
                    size="sm"
                    variant={result ? 'outline' : 'default'}
                    onClick={analyze}
                    disabled={processing || disabledReason !== null}
                    tooltip={disabledReason ?? undefined}
                    processing={processing}
                >
                    {result ? 'Relancer' : 'Analyser la vue'}
                </Button>
            </div>

            {disabledReason && (
                <Alert
                    variant="warning"
                    title="Assistant indisponible"
                    message={disabledReason}
                />
            )}
            {error && (
                <Alert
                    variant="destructive"
                    title="L'assistant n'a pas répondu"
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
                                    ? 'Problème dans le plan'
                                    : 'À surveiller dans le plan'
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
                                    {IMPACTS[index.impact].label}
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
                            Requête analysée
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
                                `Analyse le plan d'exécution de cette requête et explique-moi les index proposés :\n\n${result.query}`,
                            )
                        }
                    >
                        <MessageSquareTextIcon />
                        Approfondir dans le chat
                    </Button>
                </>
            )}
        </div>
    );
}
