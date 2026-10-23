import { useChat } from '@ai-sdk/react';
import { router, useHttp, usePage } from '@inertiajs/react';
import type { DynamicToolUIPart, UIMessage } from 'ai';
import { DefaultChatTransport } from 'ai';
import { Trash2Icon } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { PromptInputMessage } from '@/components/ai-elements/prompt-input';
import AlertDialog from '@/components/alert-dialog';
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

    return {
        pendingMessage,
        clearPendingMessage,
        messages,
        status,
        submitPromptMessage,
        error,
        streamErrorMessage,
        regenerate,
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
    const startConversationRequest = useHttp<{message: string}, SingleConversationResponse>({
        message: '',
    });
    const archiveConversationsRequest = useHttp<null, null>();
    const updateConversationsRequest = useHttp<{ title: string }, SingleConversationResponse>({
        title: ''
    });

    const loadConversations = useCallback(() => {
        loadConversationsRequest.get(ConversationController.index.url(page.props.project.id), {
            onSuccess: ({ data }) => {
                fillConversations(data);
            },
        });
    }, [conversations.length, fillConversations, page.props.project.id]);

    const startConversation = useCallback((message: PromptInputMessage) => {
        setPendingMessage(message);

        startConversationRequest.setData('message', message.text)

        startConversationRequest.post(
            ConversationController.store.url(page.props.project.id),
            {
                onSuccess: ({ conversation }) => {
                    addConversation(conversation);
                    router.visit(
                        ConversationController.conversation.url({
                            project: page.props.project.id,
                            conversation: conversation.id,
                        }),
                        {
                            viewTransition: true,
                        },
                    );
                },
            },
        );
    }, []);

    const archive = useCallback((conversation: Conversation) => {
        archiveConversationsRequest.patch(ConversationController.archive.url(conversation.id), {
            onSuccess: () => {
                removeConversation(conversation);
                toast.success(
                    'Conversation archivée, vous pouvez la retrouver dans l\'onglet "Archives" dans vos paramètres',
                );
                router.visit(
                    ConversationController.home.url(page.props.project.id),
                    {
                        viewTransition: true,
                    },
                );
            },
        });
    }, []);

    const remove = useCallback(async (conversation: Conversation) => {
        const confirmation = await AlertDialog.call({
            title: 'Supprimer la conversation ?',
            icon: Trash2Icon,
            variant: 'destructive',
            description:
                'Êtes-vous sûr de vouloir supprimer définitivement cette conversation ?',
        });

        if (!confirmation) {
            return
        }

        await archiveConversationsRequest.delete(
            ConversationController.destroy.url(conversation.id),
            {
                onSuccess: () => {
                    removeConversation(conversation);
                    toast.success(
                        'Conversation supprimée avec succès',
                    );
                    router.visit(
                        ConversationController.home.url(page.props.project.id),
                        {
                            viewTransition: true,
                        },
                    );
                },
            },
        );
    }, [page.props.project.id, removeConversation]);

    const update = useCallback(
        (
            conversation: Conversation,
            data: { title: string },
            errorCallback?: (error: {[key:string]: string}) => void,
        ) => {
            updateConversationsRequest.setData('title', data.title);

            updateConversationsRequest.post(
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

                        toast.success('Le titre a été mis à jour');
                    },
                    onError: (error) => {
                        toast.error('Une erreur est survenue : ' + error.title);
                        errorCallback?.(error);
                    },
                },
            );
        },
        [conversations],
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
