import type { DataUIPart, DynamicToolUIPart, FileUIPart, ToolUIPart, UIMessage as AiUIMessage } from 'ai';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CircleStopIcon, RefreshCcwIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import ApprovalFormRenderer from '@/components/ai/approval-form-renderer';
import ArtifactPanel from '@/components/ai/artifacts/artifact-panel';
import Confirmation from '@/components/ai/confirmation';
import MailFormRenderer from '@/components/ai/mail-form-renderer';
import ProcessingIndicator from '@/components/ai/processing-indicator';
import PromptInput from '@/components/ai/prompt-input';
import Tool from '@/components/ai/tool';
import UIMessage from '@/components/ai/ui-message';
import { Conversation as AiElementConversation, ConversationContent, ConversationScrollButton, } from '@/components/ai-elements/conversation';
import Alert from '@/components/alert';
import { useChatLayout } from '@/hooks/use-chat-layout';
import { useConversation, useConversationMessage } from '@/hooks/use-conversation';
import { ConversationPromptProvider } from '@/hooks/use-conversation-prompt';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';
import type { CustomOutputToolUIPart } from '@/types/agent';
import { AgentTools, ArtifactableTools } from '@/types/agent';

/**
 * The SDK keeps a streamed request reason as `requestReason`, while messages hydrated by Vercel::toUiMessages() carry it as `reason`.
 */
const getApprovalReason = (part: ToolUIPart): string | undefined => {
    const approval = part.approval as { reason?: string; requestReason?: string } | undefined;

    return approval?.requestReason ?? approval?.reason;
};

/**
 * The artifacts of a message, with the output of their tool call decoded.
 */
const messageArtifactables = (message: AiUIMessage): CustomOutputToolUIPart[] => {
    const artifactables: CustomOutputToolUIPart[] = [];

    message.parts.forEach((part) => {
        if (!ArtifactableTools.includes((part as ToolUIPart).type as any)) {
            return;
        }

        let output = {};

        try {
            output = JSON.parse((part as ToolUIPart).output as string);
        } catch {
            /* empty */
        }

        const withFormatedOutput = { ...part, output } as CustomOutputToolUIPart;

        // A plan that could not be computed is still shown, with the error of the engine.
        if (withFormatedOutput.output.success || part.type === AgentTools.ExplainQuery) {
            artifactables.push(withFormatedOutput);
        }
    });

    return artifactables;
};

const Conversation = () => {
    const { t } = useLaravelReactI18n();

    const { project, conversation, initialMessages } = useConversation();

    const { dataPreviewElement } = useChatLayout();

    const {
        pendingMessage,
        clearPendingMessage,
        messages,
        status,
        submitPromptMessage,
        error,
        streamErrorMessage,
        regenerate,
        cancel,
        cancelledMessageIds,
        addToolApprovalResponse,
    } = useConversationMessage(conversation, initialMessages);

    const lastUserMessageIndex = messages.findLastIndex((message) => message.role === 'user');

    const hasSelectedModel = useAgentSettingsStore((state) => state.selectedModel !== null);

    /**
     * The message typed on the home page is sent once the page is mounted for good: an effect cleaned up before, as
     * StrictMode does, stops the chat and would abort the request just made.
     */
    useEffect(() => {
        if (!pendingMessage || initialMessages.length > 0) {
            return;
        }

        const timeout = window.setTimeout(() => {
            clearPendingMessage();
            void submitPromptMessage(pendingMessage);
        });

        return () => window.clearTimeout(timeout);
    }, [pendingMessage, initialMessages.length, clearPendingMessage, submitPromptMessage]);


    const approvalRequestPart = useMemo(() => {
        const latestMessage = messages[messages.length - 1];

        return latestMessage?.parts?.find(
            (part) => (part as DynamicToolUIPart).state === 'approval-requested',
        ) as ToolUIPart | undefined;
    }, [messages])

    const conversationArtifacts = useMemo(
        () => messages.flatMap((message, messageIndex) => {
            const isMessageSaved = messageIndex !== messages.length - 1 || status === 'ready';

            return messageArtifactables(message)
                .filter((part) => part.state === 'output-available' && part.output.success)
                .map((part) => ({ part, isMessageSaved }));
        }),
        [messages, status],
    );

    const conversationPrompt = useMemo(
        () => ({
            sendPrompt: status === 'ready' && hasSelectedModel ? (text: string) => void submitPromptMessage({ text, files: [] }) : null,
        }),
        [hasSelectedModel, status, submitPromptMessage],
    );

    const handleApprovalResponse = useCallback(({id, approved}: {id: string, approved: boolean}) =>
        addToolApprovalResponse({ id, approved})
    , [addToolApprovalResponse]);

    return (
        <ConversationPromptProvider value={conversationPrompt}>
            <AiElementConversation
                resize="instant"
                initial="instant"
                className="relative size-full mask-[linear-gradient(to_bottom,black_90%,transparent_100%)]"
            >
                <ConversationContent className="m-auto max-w-4xl gap-4 pb-12">
                    {messages.map((message, messageIndex) => {
                        const isLast = messageIndex === messages.length - 1;
                        const lastTextPartIndex = message.parts.findLastIndex((part) => part.type === 'text');
                        const answerText = message.parts
                            .filter((part) => part.type === 'text')
                            .map((part) => part.text)
                            .join('\n\n');
                        const isCancelled = cancelledMessageIds.has(message.id) || message.parts.some((part) => part.type === 'data-cancelled');
                        const canRetry = status === 'ready' && (messageIndex === lastUserMessageIndex || (isLast && message.role === 'assistant'));

                        const attachments: FileUIPart[] = [];
                        const artifactables = messageArtifactables(message);
                        const fromInfo: DataUIPart<{
                            [key: string]: string;
                        }>[] = [];

                        message.parts.forEach((part) => {
                            if (part.type === 'file') {
                                attachments.push(part);
                            }

                            if (part.type === 'data-provider' || part.type === 'data-model') {
                                fromInfo.push(
                                    part as DataUIPart<{
                                        [key: string]: string;
                                    }>,
                                );
                            }
                        });

                        const parts = message.parts.map((part, i) => {
                            if (part.type === 'text') {
                                const isLastText = i === lastTextPartIndex;

                                return (
                                    <UIMessage
                                        key={`${message.id}-${i}`}
                                        message={message}
                                        messageIndex={messageIndex}
                                        part={part}
                                        attachments={attachments}
                                        status={status}
                                        isLast={isLast}
                                        partIndex={i}
                                        fromModel={fromInfo}
                                        artifactables={isLastText ? artifactables : undefined}
                                        hasActions={isLastText}
                                        copyText={answerText}
                                        isCancelled={isLastText && isCancelled}
                                        onRetry={isLastText && canRetry ? regenerate : undefined}
                                    />
                                );
                            }

                            if (part.type.startsWith('tool-')) {
                                return (
                                    <Tool
                                        part={part as ToolUIPart}
                                        key={(part as ToolUIPart).toolCallId}
                                    />
                                );
                            }

                            return null;
                        });

                        // An answer stopped before writing any text still says so, and can be asked again.
                        if (message.role === 'assistant' && isCancelled && lastTextPartIndex === -1) {
                            parts.push(
                                <div key={`${message.id}-cancelled`} className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <CircleStopIcon className="size-4" />
                                    {t('ai.messages.interrupted')}
                                </div>,
                            );
                        }

                        return parts;
                    })}

                    <ProcessingIndicator status={status} latestMessage={messages[messages.length - 1]} />

                    {(status === 'error' || error) && (
                        <Alert
                            title={t('common.error')}
                            mode="frame"
                            variant="destructive"
                            message={
                                streamErrorMessage ?? t('ai.messages.generation_failed')
                            }
                            buttons={[
                                {
                                    label: t('common.retry'),
                                    onClick: () => regenerate(),
                                    icon: RefreshCcwIcon,
                                },
                            ]}
                        />
                    )}
                </ConversationContent>
                <ConversationScrollButton className="bottom-8" />
            </AiElementConversation>

            <div className="w-full max-w-4xl">
                {(() => {
                    if (!approvalRequestPart) {
                        return (
                            <PromptInput
                                suggestions={false}
                                className="[view-transition-name:prompt-input]"
                                onSubmit={submitPromptMessage}
                                loading={status === 'streaming' || status === 'submitted'}
                                onStop={cancel}
                                variant="secondary"
                            />
                        );
                    }

                    switch (approvalRequestPart.type) {
                        case 'tool-CreateRecord':
                            return (
                                <ApprovalFormRenderer
                                    key={approvalRequestPart.toolCallId}
                                    part={approvalRequestPart}
                                    projectId={project.id}
                                    conversationId={conversation.id}
                                    onApprovalResponded={handleApprovalResponse}
                                />
                            );
                        case 'tool-SendMail':
                            return (
                                <MailFormRenderer
                                    key={approvalRequestPart.toolCallId}
                                    part={approvalRequestPart}
                                    conversationId={conversation.id}
                                    onApprovalResponded={handleApprovalResponse}
                                />
                            );
                        default:
                            return (
                                <Confirmation
                                    part={approvalRequestPart}
                                    approvalReason={getApprovalReason(
                                        approvalRequestPart,
                                    )}
                                    onApprovalResponded={handleApprovalResponse}
                                />
                            );
                    }
                })()}
            </div>

            {dataPreviewElement && createPortal(<ArtifactPanel artifacts={conversationArtifacts} />, dataPreviewElement)}
        </ConversationPromptProvider>
    );
};

export default Conversation;
