import type { DataUIPart, DynamicToolUIPart, FileUIPart, ToolUIPart } from 'ai';
import { RefreshCcwIcon, } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef } from 'react';
import ApprovalFormRenderer from '@/components/ai/approval-form-renderer';
import Confirmation from '@/components/ai/confirmation';
import ProcessingIndicator from '@/components/ai/processing-indicator';
import PromptInput from '@/components/ai/prompt-input';
import Tool from '@/components/ai/tool';
import UIMessage from '@/components/ai/ui-message';
import { Conversation as AiElementConversation, ConversationContent, ConversationScrollButton, } from '@/components/ai-elements/conversation';
import Alert from '@/components/alert';
import { useConversation, useConversationMessage } from '@/hooks/use-conversation';
import type { CustomOutputToolUIPart } from '@/types/agent';
import { ArtifactableTools } from '@/types/agent';

const Conversation = () => {

    const { conversation, initialMessages } = useConversation();

    const {
        pendingMessage,
        clearPendingMessage,
        messages,
        status,
        submitPromptMessage,
        error,
        regenerate,
        addToolApprovalResponse,
        approvalReasonsRef,
    } = useConversationMessage(conversation, initialMessages);

    const hasAutoSent = useRef(false);

    useEffect(() => {
        if (!pendingMessage || initialMessages.length > 0 || hasAutoSent.current) {
            return;
        }

        hasAutoSent.current = true;

        submitPromptMessage(pendingMessage);

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
            <AiElementConversation resize="instant" initial="instant" className="relative size-full mask-[linear-gradient(to_bottom,black_90%,transparent_100%)]">
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

                            if (ArtifactableTools.includes((part as ToolUIPart).type as any)) {

                                let output = {}

                                try {
                                    output = JSON.parse((part as ToolUIPart).output as string);
                                } catch { /* empty */ }

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

                    {(() => {
                        if (!approvalRequestPart) {
                            return null;
                        }

                        switch (approvalRequestPart.type) {
                            case 'tool-CreateRecord':
                                return (
                                    <ApprovalFormRenderer
                                        part={approvalRequestPart}
                                    />
                                );
                            default:
                                return (
                                    <Confirmation
                                        part={approvalRequestPart}
                                        approvalReason={
                                            approvalRequestPart?.approval
                                                ?.reason ||
                                            // eslint-disable-next-line react-hooks/refs
                                            approvalReasonsRef.current.get(
                                                approvalRequestPart?.approval
                                                    ?.id ?? '',
                                            )
                                        }
                                        onApprovalResponded={
                                            handleApprovalResponse
                                        }
                                    />
                                );
                        }
                    })()}

                    <ProcessingIndicator status={status} />

                    {(status === 'error' || error) && (
                        <Alert
                            title="Une erreur est survenue"
                            message="Une erreur est survenue lors de la génération de la réponse. Veuillez réessayer."
                            variant="destructive"
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
            <PromptInput
                suggestions={false}
                className="[view-transition-name:prompt-input]"
                onSubmit={submitPromptMessage}
                loading={status === 'streaming'}
                variant="secondary"
            />
        </>
    );
};

export default Conversation;
