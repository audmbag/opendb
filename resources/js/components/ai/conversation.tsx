import type { DataUIPart, DynamicToolUIPart, FileUIPart, ToolUIPart } from 'ai';
import { RefreshCcwIcon, } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef } from 'react';
import ApprovalFormRenderer from '@/components/ai/approval-form-renderer';
import Confirmation from '@/components/ai/confirmation';
import MailFormRenderer from '@/components/ai/mail-form-renderer';
import ProcessingIndicator from '@/components/ai/processing-indicator';
import PromptInput from '@/components/ai/prompt-input';
import Tool from '@/components/ai/tool';
import UIMessage from '@/components/ai/ui-message';
import { Conversation as AiElementConversation, ConversationContent, ConversationScrollButton, } from '@/components/ai-elements/conversation';
import Alert from '@/components/alert';
import { useConversation, useConversationMessage } from '@/hooks/use-conversation';
import type { CustomOutputToolUIPart } from '@/types/agent';
import { ArtifactableTools } from '@/types/agent';

/**
 * The SDK keeps a streamed request reason as `requestReason`, while messages hydrated by Vercel::toUiMessages() carry it as `reason`.
 */
const getApprovalReason = (part: ToolUIPart): string | undefined => {
    const approval = part.approval as { reason?: string; requestReason?: string } | undefined;

    return approval?.requestReason ?? approval?.reason;
};

const Conversation = () => {

    const { project, conversation, initialMessages } = useConversation();

    const {
        pendingMessage,
        clearPendingMessage,
        messages,
        status,
        submitPromptMessage,
        error,
        streamErrorMessage,
        regenerate,
        addToolApprovalResponse,
    } = useConversationMessage(conversation, initialMessages);

    const hasAutoSent = useRef(false);

    useEffect(() => {
        if (!pendingMessage || initialMessages.length > 0 || hasAutoSent.current) {
            return;
        }

        hasAutoSent.current = true;

        submitPromptMessage(pendingMessage).then(() => null);

        clearPendingMessage();

    }, [pendingMessage, initialMessages.length, clearPendingMessage, submitPromptMessage]);


    const approvalRequestPart = useMemo(() => {
        const latestMessage = messages[messages.length - 1];

        return latestMessage?.parts?.find(
            (part) => (part as DynamicToolUIPart).state === 'approval-requested',
        ) as ToolUIPart | undefined;
    }, [messages])

    const handleApprovalResponse = useCallback(({id, approved}: {id: string, approved: boolean}) =>
        addToolApprovalResponse({ id, approved})
    , [addToolApprovalResponse]);

    return (
        <>
            <AiElementConversation
                resize="instant"
                initial="instant"
                className="relative size-full mask-[linear-gradient(to_bottom,black_90%,transparent_100%)]"
            >
                <ConversationContent className="m-auto max-w-4xl gap-4 pb-12">
                    {messages.map((message, messageIndex) => {
                        const isLast = messageIndex === messages.length - 1;

                        const attachments: FileUIPart[] = [];
                        const artifactables: CustomOutputToolUIPart[] = [];
                        const fromInfo: DataUIPart<{
                            [key: string]: string;
                        }>[] = [];

                        message.parts.forEach((part) => {
                            if (part.type === 'file') {
                                attachments.push(part);
                            }

                            if (part.type.startsWith('data-')) {
                                fromInfo.push(
                                    part as DataUIPart<{
                                        [key: string]: string;
                                    }>,
                                );
                            }

                            if (
                                ArtifactableTools.includes(
                                    (part as ToolUIPart).type as any,
                                )
                            ) {
                                let output = {};

                                try {
                                    output = JSON.parse(
                                        (part as ToolUIPart).output as string,
                                    );
                                } catch {
                                    /* empty */
                                }

                                const withFormatedOutput = {
                                    ...part,
                                    output,
                                } as CustomOutputToolUIPart;

                                if (withFormatedOutput.output.success) {
                                    artifactables.push(
                                        withFormatedOutput as CustomOutputToolUIPart,
                                    );
                                }
                            }
                        });

                        return message.parts.map((part, i) => {
                            if (part.type === 'text') {
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
                                        artifactables={artifactables}
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
                    })}

                    <ProcessingIndicator status={status} />

                    {(status === 'error' || error) && (
                        <Alert
                            title="Une erreur est survenue"
                            mode="frame"
                            variant="destructive"
                            message={
                                streamErrorMessage ??
                                'Une erreur est survenue lors de la génération de la réponse. Veuillez réessayer.'
                            }
                            buttons={[
                                {
                                    label: 'Réessayer',
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
                                loading={status === 'streaming'}
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
        </>
    );
};

export default Conversation;
