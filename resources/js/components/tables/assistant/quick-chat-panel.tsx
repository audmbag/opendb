import { useChat } from '@ai-sdk/react';
import type { DataUIPart, FileUIPart, ToolUIPart } from 'ai';
import type { UIMessage as ChatMessage } from 'ai';
import { DefaultChatTransport } from 'ai';
import {
    EraserIcon,
    MessageSquareTextIcon,
    MessagesSquareIcon,
    RefreshCcwIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import ModelSelector from '@/components/ai/model-selector';
import ProcessingIndicator from '@/components/ai/processing-indicator';
import Tool from '@/components/ai/tool';
import UIMessage from '@/components/ai/ui-message';
import {
    Conversation,
    ConversationContent,
    ConversationScrollButton,
} from '@/components/ai-elements/conversation';
import type { PromptInputMessage } from '@/components/ai-elements/prompt-input';
import {
    PromptInput,
    PromptInputBody,
    PromptInputFooter,
    PromptInputSubmit,
    PromptInputTextarea,
    PromptInputTools,
} from '@/components/ai-elements/prompt-input';
import { Suggestion, Suggestions } from '@/components/ai-elements/suggestion';
import Alert from '@/components/alert';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';
import TableAssistantController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableAssistantController';
import type { TableView } from '../types';
import { viewQuery } from '../view';

type Props = {
    projectId: string;
    table: string;
    view: TableView;
    disabledReason: string | null;
    /**
     * The messages exchanged so far, kept by the tab so that closing the panel does not lose them.
     */
    messages: ChatMessage[];
    onMessagesChange: (messages: ChatMessage[]) => void;
    onAskChat: (question: string) => void;
};

const NO_MODEL_MESSAGE =
    "Choisissez un modèle d'IA sous le champ de saisie pour poser une question.";

const SUGGESTIONS = [
    'Combien de lignes correspondent à mes filtres ?',
    'Quelles colonnes sont souvent vides ?',
    'Résume les lignes affichées',
];

/**
 * The message to show for a request the server refused before streaming, like a model that is no longer available.
 */
function requestErrorMessage(error: Error): string {
    try {
        const body = JSON.parse(error.message) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (body.errors?.provider || body.errors?.model) {
            return NO_MODEL_MESSAGE;
        }

        return body.message ?? error.message;
    } catch {
        return "L'assistant n'a pas pu répondre. Vérifiez le modèle choisi puis réessayez.";
    }
}

/**
 * A quick question about the table, answered next to its rows without starting a conversation of the chat.
 * The agent knows the structure of the table and the filters of the view, and only reads the database.
 * The messages, tools and input are those of the chat, in a narrower panel.
 */
export default function QuickChatPanel({
    projectId,
    table,
    view,
    disabledReason,
    messages: initialMessages,
    onMessagesChange,
    onAskChat,
}: Props) {
    const [input, setInput] = useState('');
    const [streamError, setStreamError] = useState<string | null>(null);
    const receivedStreamError = useRef<string | null>(null);
    const hasSelectedModel = useAgentSettingsStore(
        (state) => state.selectedModel !== null,
    );

    const transport = useMemo(
        () =>
            new DefaultChatTransport({
                api: TableAssistantController.chat.url({
                    project: projectId,
                    table: encodeURIComponent(table),
                }),
                headers: {
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    Accept: 'application/json',
                },
            }),
        [projectId, table],
    );

    const {
        messages,
        sendMessage,
        status,
        error,
        stop,
        setMessages,
        regenerate,
    } = useChat({
        id: `table-chat:${projectId}:${table}`,
        transport,
        messages: initialMessages,
        onData: (part) => {
            if (part.type === 'data-stream-error') {
                receivedStreamError.current = (
                    part.data as { message: string }
                ).message;
            }
        },
        onError: () => {
            setStreamError(receivedStreamError.current);
            receivedStreamError.current = null;
        },
    });

    useEffect(() => {
        onMessagesChange(messages);
    }, [messages, onMessagesChange]);

    const busy = status === 'submitted' || status === 'streaming';

    /**
     * The filters are those of the moment of the request: asked after changing them, a question is about the new rows.
     */
    const requestBody = () => {
        const { selectedModel } = useAgentSettingsStore.getState();

        return {
            ...viewQuery(view, { withPage: false }),
            provider: selectedModel?.providerId ?? null,
            model: selectedModel?.modelId ?? null,
        };
    };

    const ask = (text: string) => {
        const question = text.trim();

        if (!question || busy || disabledReason || !hasSelectedModel) {
            return;
        }

        setStreamError(null);
        setInput('');
        sendMessage({ text: question }, { body: requestBody() });
    };

    const lastQuestion = messages
        .findLast((message) => message.role === 'user')
        ?.parts.find((part) => part.type === 'text')?.text;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            {messages.length === 0 ? (
                <div className="flex min-h-0 flex-1 flex-col justify-center gap-4 overflow-y-auto p-4">
                    {disabledReason ? (
                        <Alert
                            variant="warning"
                            title="Assistant indisponible"
                            message={disabledReason}
                        />
                    ) : (
                        <>
                            <EmptyState
                                icon={MessagesSquareIcon}
                                title={`Une question sur ${table} ?`}
                                description="L'assistant connaît sa structure et vos filtres, et lit la base sans la modifier. Cet échange n'est pas enregistré dans vos conversations."
                                className="flex-none p-0"
                            />
                            <Suggestions className="w-full flex-col items-stretch">
                                {SUGGESTIONS.map((suggestion) => (
                                    <Suggestion
                                        key={suggestion}
                                        suggestion={suggestion}
                                        onClick={ask}
                                        disabled={!hasSelectedModel}
                                        className="justify-start rounded-lg"
                                    />
                                ))}
                            </Suggestions>
                        </>
                    )}
                </div>
            ) : (
                <Conversation
                    resize="instant"
                    initial="instant"
                    className="relative min-h-0 flex-1"
                >
                    <ConversationContent className="gap-4 p-4">
                        {messages.map((message, messageIndex) => {
                            const isLast = messageIndex === messages.length - 1;
                            const attachments = message.parts.filter(
                                (part): part is FileUIPart =>
                                    part.type === 'file',
                            );
                            const fromModel = message.parts.filter((part) =>
                                part.type.startsWith('data-'),
                            ) as DataUIPart<{ [key: string]: string }>[];

                            return message.parts.map((part, partIndex) => {
                                if (part.type === 'text') {
                                    return (
                                        <UIMessage
                                            key={`${message.id}-${partIndex}`}
                                            message={message}
                                            messageIndex={1}
                                            part={part}
                                            attachments={attachments}
                                            status={status}
                                            isLast={isLast}
                                            partIndex={partIndex}
                                            fromModel={fromModel}
                                        />
                                    );
                                }

                                if (part.type.startsWith('tool-')) {
                                    return (
                                        <Tool
                                            key={
                                                (part as ToolUIPart).toolCallId
                                            }
                                            part={part as ToolUIPart}
                                        />
                                    );
                                }

                                return null;
                            });
                        })}

                        <ProcessingIndicator
                            status={status}
                            latestMessage={messages[messages.length - 1]}
                        />

                        {error && (
                            <Alert
                                title="Une erreur est survenue"
                                mode="frame"
                                variant="destructive"
                                message={
                                    streamError ?? requestErrorMessage(error)
                                }
                                buttons={[
                                    {
                                        label: 'Réessayer',
                                        icon: RefreshCcwIcon,
                                        onClick: () =>
                                            regenerate({ body: requestBody() }),
                                    },
                                ]}
                            />
                        )}
                    </ConversationContent>
                    <ConversationScrollButton />
                </Conversation>
            )}

            <div className="flex flex-col gap-2 border-t p-3">
                <PromptInput
                    onSubmit={(message: PromptInputMessage) =>
                        ask(message.text)
                    }
                >
                    <PromptInputBody>
                        <PromptInputTextarea
                            value={input}
                            onChange={(event) => setInput(event.target.value)}
                            placeholder={
                                disabledReason ?? `Une question sur ${table}…`
                            }
                            disabled={!!disabledReason}
                            className="min-h-12"
                            aria-label="Question sur la table"
                        />
                    </PromptInputBody>
                    <PromptInputFooter className="p-2 pt-0">
                        <PromptInputTools>
                            <ModelSelector size="xs" />
                        </PromptInputTools>
                        <PromptInputSubmit
                            status={status}
                            onStop={stop}
                            disabled={
                                !busy &&
                                (!input.trim() ||
                                    !!disabledReason ||
                                    !hasSelectedModel)
                            }
                        />
                    </PromptInputFooter>
                </PromptInput>
                {messages.length > 0 && (
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={EraserIcon}
                            disabled={busy}
                            onClick={() => {
                                setMessages([]);
                                setStreamError(null);
                            }}
                        >
                            Effacer
                        </Button>
                        {lastQuestion && (
                            <Button
                                variant="ghost"
                                size="sm"
                                icon={MessageSquareTextIcon}
                                disabled={busy}
                                onClick={() => onAskChat(lastQuestion)}
                                tooltip="Poser la dernière question dans une conversation du chat"
                            >
                                Continuer dans le chat
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
