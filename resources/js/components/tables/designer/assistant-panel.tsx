import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CheckIcon,
    EraserIcon,
    RotateCcwIcon,
    SparklesIcon,
    Undo2Icon,
    XIcon,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import ProcessingIndicator from '@/components/ai/processing-indicator';
import {
    Conversation,
    ConversationContent,
    ConversationScrollButton,
} from '@/components/ai-elements/conversation';
import { Message, MessageContent } from '@/components/ai-elements/message';
import { Suggestion, Suggestions } from '@/components/ai-elements/suggestion';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import { Badge } from '@/components/reui/badge';
import type { BadgeProps } from '@/components/reui/badge';
import { cn } from '@/lib/utils';
import AiPromptInput from '../assistant/ai-prompt-input';
import type { AssistantTurn, ProposalChanges } from './proposal';

type Props = {
    open: boolean;
    onClose: () => void;
    turns: AssistantTurn[];
    /**
     * Whether the form already holds a table to build on: the field then asks for a change.
     */
    refining: boolean;
    processing: boolean;
    error: string | null;
    onAsk: (prompt: string) => Promise<boolean>;
    onAccept: () => void;
    onReject: () => void;
    onRestart: () => void;
};

const SUGGESTIONS = [
    'tables.designer.assistant.suggestions.articles',
    'tables.designer.assistant.suggestions.bookings',
    'tables.designer.assistant.suggestions.logins',
];

/**
 * What the assistant says it does while it works, one after the other: it reads the database, then designs the table.
 */
const STEPS = [
    'tables.designer.assistant.steps.reading',
    'tables.designer.assistant.steps.designing',
    'tables.designer.assistant.steps.checking',
];

/**
 * The width of the panel, kept by its content while it slides, so that nothing reflows.
 */
const ASSISTANT_PANEL_WIDTH = 'w-[24rem]';

/**
 * The assistant of the designer of a new table, slid out of the right side of the designer: the user describes the
 * table in their words, follows what the assistant does to the form beside it, and keeps or rejects each proposal.
 */
export default function AssistantPanel({
    open,
    onClose,
    turns,
    refining,
    processing,
    error,
    onAsk,
    onAccept,
    onReject,
    onRestart,
}: Props) {
    const { t } = useLaravelReactI18n();
    const panel = useRef<HTMLDivElement>(null);
    const deciding = turns.some((turn) => turn.outcome === 'proposed');

    useEffect(() => {
        if (open) {
            panel.current
                ?.querySelector<HTMLTextAreaElement>('textarea')
                ?.focus({ preventScroll: true });
        }
    }, [open]);

    return (
        <aside
            aria-label={t('tables.designer.assistant.title')}
            inert={!open}
            className={cn(
                'relative flex-none overflow-hidden border-l transition-[width,opacity,border-color] duration-300 ease-out motion-reduce:transition-none',
                open
                    ? `${ASSISTANT_PANEL_WIDTH} opacity-100`
                    : 'w-0 border-transparent opacity-0',
            )}
        >
            <div
                ref={panel}
                className={cn(
                    'flex h-full flex-col transition-transform duration-300 ease-out motion-reduce:transition-none',
                    ASSISTANT_PANEL_WIDTH,
                    open ? 'translate-x-0' : 'translate-x-8',
                )}
            >
                <div className="flex items-center gap-2 border-b px-3 py-2">
                    <SparklesIcon className="size-4 shrink-0 text-muted-foreground" />
                    <h2 className="min-w-0 flex-1 truncate text-sm font-medium">
                        {t('tables.designer.assistant.title')}
                    </h2>
                    {turns.length > 0 && (
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={onRestart}
                            disabled={processing || deciding}
                            aria-label={t('tables.designer.assistant.restart')}
                            tooltip={t('tables.designer.assistant.restart')}
                        >
                            <EraserIcon />
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={onClose}
                        aria-label={t('tables.designer.assistant.close')}
                        tooltip={[
                            t('tables.designer.assistant.close'),
                            ['Ctrl', 'I'],
                        ]}
                    >
                        <XIcon />
                    </Button>
                </div>

                {turns.length === 0 ? (
                    <div className="flex min-h-0 flex-1 flex-col justify-center gap-4 overflow-y-auto px-3 py-4">
                        <EmptyState
                            icon={SparklesIcon}
                            title={t('tables.designer.assistant.empty_title')}
                            description={t(
                                'tables.designer.assistant.empty_description',
                            )}
                            className="flex-none p-0"
                        />
                        <Suggestions className="w-full flex-col items-stretch">
                            {SUGGESTIONS.map((suggestion) => (
                                <Suggestion
                                    key={suggestion}
                                    suggestion={t(suggestion)}
                                    onClick={(text) => void onAsk(text)}
                                    disabled={processing}
                                    className="h-auto justify-start rounded-lg py-2 text-left whitespace-normal"
                                />
                            ))}
                        </Suggestions>
                    </div>
                ) : (
                    <Conversation
                        resize="instant"
                        initial="instant"
                        className="relative min-h-0 flex-1"
                    >
                        <ConversationContent className="gap-4 px-3 py-4">
                            {turns.map((turn) => (
                                <Turn
                                    key={turn.id}
                                    turn={turn}
                                    onAccept={onAccept}
                                    onReject={onReject}
                                />
                            ))}
                        </ConversationContent>
                        <ConversationScrollButton />
                    </Conversation>
                )}

                <div className="border-t p-3">
                    <AiPromptInput
                        multiline
                        icon={SparklesIcon}
                        label={t('tables.designer.assistant.label')}
                        placeholder={
                            refining
                                ? t(
                                      'tables.designer.assistant.refine_placeholder',
                                  )
                                : t('tables.designer.assistant.placeholder')
                        }
                        onSubmit={onAsk}
                        processing={processing}
                        error={error}
                        maxLength={2000}
                    />
                </div>
            </div>
        </aside>
    );
}

/**
 * A request of the user and what the assistant made of it: the steps it goes through while it works, then its answer
 * and the table it proposes.
 */
function Turn({
    turn,
    onAccept,
    onReject,
}: {
    turn: AssistantTurn;
    onAccept: () => void;
    onReject: () => void;
}) {
    const { t } = useLaravelReactI18n();

    return (
        <>
            <Message from="user">
                <MessageContent className="whitespace-pre-wrap group-[.is-user]:px-3 group-[.is-user]:py-2">
                    {turn.prompt}
                </MessageContent>
            </Message>
            {turn.outcome === 'pending' ? (
                <ProcessingIndicator
                    status="submitted"
                    latestMessage={undefined}
                    awaitingLabels={STEPS.map((key) => t(key))}
                />
            ) : (
                <Message from="assistant" className="max-w-full">
                    {turn.answer && (
                        <MessageContent
                            className={cn(
                                'text-pretty',
                                turn.outcome === 'declined' &&
                                    'text-muted-foreground',
                            )}
                        >
                            {turn.answer}
                        </MessageContent>
                    )}
                    {turn.changes && (
                        <ProposalCard
                            changes={turn.changes}
                            outcome={turn.outcome}
                            onAccept={onAccept}
                            onReject={onReject}
                        />
                    )}
                </Message>
            )}
        </>
    );
}

/**
 * What a proposal changed in the form, and the choice of the user while it is the one shown in the form.
 */
function ProposalCard({
    changes,
    outcome,
    onAccept,
    onReject,
}: {
    changes: ProposalChanges;
    outcome: AssistantTurn['outcome'];
    onAccept: () => void;
    onReject: () => void;
}) {
    const { t, tChoice } = useLaravelReactI18n();
    const added = Object.values(changes.columns).filter(
        (mark) => mark === 'added',
    ).length;
    const changed = Object.values(changes.columns).length - added;
    const facts: { label: string; variant: BadgeProps['variant'] }[] = [
        changes.renamed && {
            label: t('tables.designer.assistant.renamed_table'),
            variant: 'warning-light' as const,
        },
        added > 0 && {
            label: tChoice('tables.designer.assistant.added_columns', added),
            variant: 'success-light' as const,
        },
        changed > 0 && {
            label: tChoice(
                'tables.designer.assistant.changed_columns',
                changed,
            ),
            variant: 'warning-light' as const,
        },
        changes.removedColumns.length > 0 && {
            label: tChoice(
                'tables.designer.assistant.removed_columns',
                changes.removedColumns.length,
            ),
            variant: 'destructive-light' as const,
        },
        changes.indexes.size > 0 && {
            label: tChoice(
                'tables.designer.assistant.indexes',
                changes.indexes.size,
            ),
            variant: 'success-light' as const,
        },
        changes.foreignKeys.size > 0 && {
            label: tChoice(
                'tables.designer.assistant.foreign_keys',
                changes.foreignKeys.size,
            ),
            variant: 'success-light' as const,
        },
    ].filter((fact) => fact !== false);
    const current = outcome === 'proposed';
    const OutcomeIcon =
        outcome === 'accepted'
            ? CheckIcon
            : outcome === 'rejected'
              ? Undo2Icon
              : RotateCcwIcon;

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-lg border bg-background p-3',
                current ? 'shadow-xs' : 'opacity-80',
            )}
        >
            <div className="flex flex-wrap gap-1.5">
                {facts.length === 0 && (
                    <span className="text-xs text-muted-foreground">
                        {t('tables.designer.assistant.unchanged')}
                    </span>
                )}
                {facts.map((fact) => (
                    <Badge key={fact.label} variant={fact.variant}>
                        {fact.label}
                    </Badge>
                ))}
            </div>
            {changes.removedColumns.length > 0 && (
                <p className="font-mono text-xs text-muted-foreground line-through decoration-destructive/60">
                    {changes.removedColumns.join(', ')}
                </p>
            )}
            {current ? (
                <div className="grid grid-cols-2 gap-2">
                    <Button variant="outline" size="sm" onClick={onReject}>
                        <XIcon />
                        {t('tables.designer.assistant.reject')}
                    </Button>
                    <Button size="sm" onClick={onAccept}>
                        <CheckIcon />
                        {t('tables.designer.assistant.accept')}
                    </Button>
                </div>
            ) : (
                <p
                    className={cn(
                        'flex items-center gap-1.5 text-xs',
                        outcome === 'accepted'
                            ? 'text-success'
                            : 'text-muted-foreground',
                    )}
                >
                    <OutcomeIcon className="size-3.5" />
                    {t(`tables.designer.assistant.outcomes.${outcome}`)}
                </p>
            )}
        </div>
    );
}
