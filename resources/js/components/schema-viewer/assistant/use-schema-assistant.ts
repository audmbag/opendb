import { useChat } from '@ai-sdk/react';
import type { ToolUIPart, UIMessage } from 'ai';
import { DefaultChatTransport } from 'ai';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';
import ErdAssistantController from '@/wayfinder/actions/App/Http/Controllers/Erd/ErdAssistantController';
import type { SchemaChangeResult, SchemaProposal } from './types';

export const SCHEMA_TOOL = 'tool-ProposeSchemaChanges';

type Options = {
    projectId: string;
    /**
     * Called once each answer of the assistant ends, with what the changes it made did, or null when it ran none:
     * the result tells the changes that ran, even when the database refused one of them after the others.
     */
    onFinished: (result: SchemaChangeResult | null) => void;
};

/**
 * The output of the tool, sent as the JSON text it returned.
 */
export function schemaChangeResult(
    part: ToolUIPart,
): SchemaChangeResult | null {
    try {
        return typeof part.output === 'string'
            ? (JSON.parse(part.output) as SchemaChangeResult)
            : ((part.output as SchemaChangeResult | undefined) ?? null);
    } catch {
        return null;
    }
}

/**
 * The exchange with the assistant of the diagram, kept by the page so that the panel can be closed without losing
 * it, and the changes it proposes, waiting for the user to apply or reject them.
 */
export function useSchemaAssistant({ projectId, onFinished }: Options) {
    const [streamError, setStreamError] = useState<string | null>(null);
    const receivedStreamError = useRef<string | null>(null);
    const sentApprovals = useRef<Set<string>>(new Set());
    const handledResults = useRef<Set<string>>(new Set());
    const onFinishedRef = useRef(onFinished);

    useEffect(() => {
        onFinishedRef.current = onFinished;
    }, [onFinished]);

    const transport = useMemo(
        () =>
            new DefaultChatTransport({
                api: ErdAssistantController.chat.url({ project: projectId }),
                headers: {
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                    Accept: 'application/json',
                },
                // The model chosen at the moment of each request, also when it follows an approval.
                body: () => {
                    const { selectedModel } = useAgentSettingsStore.getState();

                    return {
                        provider: selectedModel?.providerId ?? null,
                        model: selectedModel?.modelId ?? null,
                    };
                },
            }),
        [projectId],
    );

    /**
     * Once the user answered every approval of the last message, the answers are sent once.
     */
    const sendAutomaticallyWhen = useCallback(
        ({ messages }: { messages: UIMessage[] }) => {
            const last = messages[messages.length - 1];

            if (!last || last.role !== 'assistant') {
                return false;
            }

            const parts = last.parts as ToolUIPart[];

            if (parts.some((part) => part.state === 'approval-requested')) {
                return false;
            }

            const responded = parts.filter(
                (part) => part.state === 'approval-responded',
            );
            const batch = responded
                .map((part) => part.approval?.id)
                .sort()
                .join(',');

            if (!responded.length || sentApprovals.current.has(batch)) {
                return false;
            }

            sentApprovals.current.add(batch);

            return true;
        },
        [],
    );

    const chat = useChat({
        id: `erd-assistant:${projectId}`,
        transport,
        sendAutomaticallyWhen,
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
            onFinishedRef.current(null);
        },
        // The diagram is read again as soon as the changes ran, whether the user applied them or they ran at once.
        onFinish: ({ message }) => {
            let ran: SchemaChangeResult | null = null;

            message.parts.forEach((part) => {
                const toolPart = part as ToolUIPart;

                if (
                    toolPart.type !== SCHEMA_TOOL ||
                    toolPart.state !== 'output-available' ||
                    handledResults.current.has(toolPart.toolCallId)
                ) {
                    return;
                }

                handledResults.current.add(toolPart.toolCallId);

                const result = schemaChangeResult(toolPart);

                // Changes refused before running anything are fixed by the assistant itself.
                if (result && !result.errors) {
                    ran = result;
                }
            });

            onFinishedRef.current(ran);
        },
    });

    const { messages, addToolApprovalResponse } = chat;

    const proposal = useMemo<SchemaProposal | null>(() => {
        const last = messages[messages.length - 1];

        if (!last || last.role !== 'assistant') {
            return null;
        }

        const part = (last.parts as ToolUIPart[]).findLast(
            (item) =>
                item.type === SCHEMA_TOOL &&
                item.state === 'approval-requested',
        );

        if (!part?.approval) {
            return null;
        }

        const input = part.input as { changes?: Record<string, unknown>[] };

        return {
            toolCallId: part.toolCallId,
            approvalId: part.approval.id,
            changes: input.changes ?? [],
        };
    }, [messages]);

    const respond = useCallback(
        (approved: boolean) => {
            if (proposal) {
                void addToolApprovalResponse({
                    id: proposal.approvalId,
                    approved,
                });
            }
        },
        [addToolApprovalResponse, proposal],
    );

    const clearStreamError = useCallback(() => setStreamError(null), []);

    return { ...chat, streamError, clearStreamError, proposal, respond };
}

export type SchemaAssistant = ReturnType<typeof useSchemaAssistant>;
