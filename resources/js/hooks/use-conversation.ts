import { useChat } from '@ai-sdk/react';
import { router, useHttp, usePage } from '@inertiajs/react';
import type { DynamicToolUIPart, UIMessage } from 'ai';
import { DefaultChatTransport } from 'ai';
import { Trash2Icon } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { PromptInputMessage } from '@/components/ai-elements/prompt-input';
import AlertDialog from '@/components/alert-dialog';
import { useProjectSettingsDialog } from '@/hooks/use-project-settings-modal';
import { responseMessage } from '@/lib/http-errors';
import { trans } from '@/lib/i18n';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';
import { useConversationStore } from '@/store/use-conversation-store';
import type { APIData } from '@/types/global';
import type { Conversation } from '@/types/models/conversation';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';


type SingleConversationResponse = {
    conversation: Conversation;
}

type StreamErrorData = {
    message: string;
}

const useConversationMessage = (
    conversation: Conversation,
    initialMessages: UIMessage[],
) => {

    const transport = useMemo(() => {
        return new DefaultChatTransport({
            api: ConversationController.chat(conversation.id).url,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            // Read on every request, so retries and approval responses use the current settings too.
            body: () => {
                const { accessMode, effort, selectedModel } = useAgentSettingsStore.getState();

                return {
                    access_mode: accessMode,
                    effort,
                    provider: selectedModel?.providerId ?? null,
                    model: selectedModel?.modelId ?? null,
                };
            },
        });
    }, [conversation.id]);

    const pendingMessage = useConversationStore((state) => state.pendingMessage);
    const clearPendingMessage = useConversationStore((state) => state.clearPendingMessage);

    const sentApprovalBatchesRef = useRef<Set<string>>(new Set());

    /**
     * The server sends the message explaining a stream error just before the error itself,
     * so it is held until the error tells it belongs to the failed request.
     */
    const receivedStreamErrorMessageRef = useRef<string | null>(null);
    const [streamErrorMessage, setStreamErrorMessage] = useState<string | null>(null);

    const sendAutomaticallyWhen = useCallback(({ messages }: { messages: UIMessage[] }) => {
            const last = messages[messages.length - 1];

            if (!last || last.role !== 'assistant') {
                return false;
            }

            const stillPending = last.parts.some(
                (p) => (p as DynamicToolUIPart).state === 'approval-requested',
            );

            if (stillPending) {
                return false;
            }

            const responded = last.parts.filter(
                (p): p is DynamicToolUIPart =>
                    (p as DynamicToolUIPart).state === 'approval-responded',
            );

            if (responded.length === 0) {
                return false;
            }

            const batchKey = responded
                .map((p) => p.approval!.id)
                .sort()
                .join(',');

            if (sentApprovalBatchesRef.current.has(batchKey)) {
                return false;
            }

            sentApprovalBatchesRef.current.add(batchKey);

            return true;
        },
        [],
    );
    const {
        messages,
        sendMessage,
        status,
        error,
        regenerate,
        addToolApprovalResponse,
        stop,
    } = useChat({
        id: conversation.id,
        transport: transport,
        messages: initialMessages,
        sendAutomaticallyWhen,
        onData: (part) => {
            if (part.type === 'data-stream-error') {
                receivedStreamErrorMessageRef.current = (part.data as StreamErrorData).message;
            }
        },
        onError: () => {
            setStreamErrorMessage(receivedStreamErrorMessageRef.current);
            receivedStreamErrorMessageRef.current = null;
        },
    });

    const submitPromptMessage = useCallback(
        async (message: PromptInputMessage) => {

            if (message.text.trim()) {
                await sendMessage(message);
            }
        },
        [sendMessage],
    );

    const cancelRequest = useHttp<null, null>();

    /**
     * The answers stopped since the page opened: those saved before are marked by a `data-cancelled` part.
     */
    const [cancelledMessageIds, setCancelledMessageIds] = useState<ReadonlySet<string>>(new Set());

    /**
     * Stop reading the answer at once, and ask the server to stop the agent before its next step.
     */
    const cancel = useCallback(() => {
        const latestMessage = messages[messages.length - 1];

        cancelRequest.post(ConversationController.cancel.url(conversation.id));
        void stop();

        if (latestMessage?.role === 'assistant') {
            setCancelledMessageIds((ids) => new Set(ids).add(latestMessage.id));
        }
    }, [cancelRequest, conversation.id, messages, stop]);

    /**
     * Answer the last message of the user again: the server forgets the previous answer.
     */
    const retry = useCallback(() => {
        setStreamErrorMessage(null);
        void regenerate();
    }, [regenerate]);

    return {
        pendingMessage,
        clearPendingMessage,
        messages,
        status,
        submitPromptMessage,
        error,
        streamErrorMessage,
        regenerate: retry,
        cancel,
        cancelledMessageIds,
        addToolApprovalResponse,
    };
};

const useConversation = () => {

    const {
        props: { project, conversation, initialMessages },
    } = usePage<{
        project: Project;
        conversation: Conversation;
        initialMessages: UIMessage[];
    }>();

    const conversations = useConversationStore((state) => state.conversations)
    const fillConversations = useConversationStore((state) => state.fill);
    const addConversation = useConversationStore((state) => state.add);
    const removeConversation = useConversationStore((state) => state.remove);
    const setPendingMessage = useConversationStore((state) => state.setPendingMessage);

    const page = usePage<{project: Project}>()

    const loadConversationsRequest = useHttp<null, APIData<Conversation>>();
    const startConversationRequest = useHttp<{message: string, provider: string | null, model: string | null}, SingleConversationResponse>({
        message: '',
        provider: null,
        model: null,
    });
    const archiveConversationsRequest = useHttp<null, null>();
    const updateConversationsRequest = useHttp<{ title: string }, SingleConversationResponse>({
        title: ''
    });

    // The request methods of useHttp keep their identity between renders, unlike the object holding them.
    const { get: getConversations } = loadConversationsRequest;
    const { setData: setStartData, post: postStart } = startConversationRequest;
    const { patch: patchConversation, delete: deleteConversation } = archiveConversationsRequest;
    const { setData: setUpdateData, post: postUpdate } = updateConversationsRequest;

    const { open: openProjectSettings } = useProjectSettingsDialog();

    const projectId = page.props.project.id;

    const loadConversations = useCallback(() => {
        getConversations(ConversationController.index.url(projectId), {
            onSuccess: ({ data }) => {
                fillConversations(data);
            },
        });
    }, [getConversations, fillConversations, projectId]);

    const startConversation = useCallback((message: PromptInputMessage) => {
        setPendingMessage(message);

        const { selectedModel } = useAgentSettingsStore.getState();

        setStartData({
            message: message.text,
            provider: selectedModel?.providerId ?? null,
            model: selectedModel?.modelId ?? null,
        });

        postStart(
            ConversationController.store.url(projectId),
            {
                onSuccess: ({ conversation }) => {
                    addConversation(conversation);
                    router.visit(
                        ConversationController.conversation.url({
                            project: projectId,
                            conversation: conversation.id,
                        }),
                        {
                            viewTransition: true,
                        },
                    );
                },
                onError: () => {
                    setPendingMessage(null);
                    toast.error(trans('conversations.start_failed'), {
                        description: trans('conversations.start_failed_description'),
                    });
                },
                onHttpException: (response) => {
                    setPendingMessage(null);
                    toast.error(trans('conversations.start_failed'), {
                        description: responseMessage(response) ?? trans('common.unexpected_error'),
                    });
                },
            },
        ).catch(() => null);
    }, [addConversation, postStart, projectId, setPendingMessage, setStartData]);

    const archive = useCallback((conversation: Conversation) => {
        patchConversation(ConversationController.archive.url(conversation.id), {
            onSuccess: () => {
                removeConversation(conversation);
                toast.success(trans('conversations.archived'), {
                    description: trans('conversations.archived_description'),
                    action: {
                        label: trans('conversations.see_archives'),
                        onClick: () => openProjectSettings('archives'),
                    },
                });
                router.visit(
                    ConversationController.home.url(projectId),
                    {
                        viewTransition: true,
                    },
                );
            },
        });
    }, [openProjectSettings, patchConversation, projectId, removeConversation]);

    const remove = useCallback(async (conversation: Conversation) => {
        const confirmation = await AlertDialog.call({
            title: trans('conversations.delete_title'),
            icon: Trash2Icon,
            variant: 'destructive',
            description: trans('conversations.delete_description'),
        });

        if (!confirmation) {
            return
        }

        await deleteConversation(
            ConversationController.destroy.url(conversation.id),
            {
                onSuccess: () => {
                    removeConversation(conversation);
                    toast.success(trans('conversations.deleted'));
                    router.visit(
                        ConversationController.home.url(projectId),
                        {
                            viewTransition: true,
                        },
                    );
                },
            },
        );
    }, [deleteConversation, projectId, removeConversation]);

    const update = useCallback(
        (
            conversation: Conversation,
            data: { title: string },
            errorCallback?: (error: {[key:string]: string}) => void,
        ) => {
            setUpdateData('title', data.title);

            postUpdate(
                ConversationController.update.url(conversation.id),
                {
                    onSuccess: (response) => {
                        const updatedConversationList = structuredClone(
                            conversations,
                        ).map((c) => {
                            if (c.id === response.conversation.id) {
                                return {
                                    ...c,
                                    title: response.conversation.title,
                                };
                            }

                            return c;
                        });

                        fillConversations(updatedConversationList);

                        toast.success(trans('conversations.title_updated'));
                    },
                    onError: (error) => {
                        toast.error(trans('common.error_with_message', { message: error.title }));
                        errorCallback?.(error);
                    },
                },
            );
        },
        [conversations, fillConversations, postUpdate, setUpdateData],
    );

    return {
        project,
        conversation,
        initialMessages,

        conversations,
        startConversation,
        loadConversations,
        startConversationRequest,
        loadConversationsRequest,
        updateConversationsRequest,
        archive,
        remove,
        update,
    };
}



export { useConversation, useConversationMessage };
