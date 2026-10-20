import { useChat } from '@ai-sdk/react';
import { router, useHttp, usePage } from '@inertiajs/react';
import type {
    DynamicToolUIPart,
    FileUIPart,
    ToolApprovalResponse,
    ToolUIPart,
    UIMessage,
} from 'ai';
import { DefaultChatTransport } from 'ai';
import { Trash2Icon } from 'lucide-react';
import { useCallback, useMemo, useReducer, useRef } from 'react';
import { toast } from 'sonner';
import type { PromptInputMessage } from '@/components/ai-elements/prompt-input';
import AlertDialog from '@/components/alert-dialog';
import { dataUrlToBlob } from '@/lib/utils';
import { useConversationStore } from '@/store/use-conversation-store';
import type { APIData } from '@/types/global';
import type { Conversation } from '@/types/models/conversation';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';


type SingleConversationResponse = {
    conversation: Conversation;
}

const useConversationMessage = (
    conversation: Conversation,
    initialMessages: UIMessage[],
) => {

    const approvalReasonsRef = useRef<Map<string, string>>(new Map());
    const [, forceRerender] = useReducer((x) => x + 1, 0);

    const sniffApprovalReasons = useCallback((stream: ReadableStream<Uint8Array>) => {
        const reader = stream.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        (async () => {
            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                for (const line of lines) {
                    if (!line.startsWith('data: ')) {
                        continue;
                    }

                    try {
                        const chunk = JSON.parse(line.slice(6));

                        if (
                            chunk.type === 'tool-approval-request' &&
                            chunk.reason
                        ) {
                            approvalReasonsRef.current.set(
                                chunk.approvalId,
                                chunk.reason,
                            );
                            forceRerender();
                        }
                    } catch { /* empty */ }
                }
            }
        })();
    }, []);

    const transport = useMemo(() => {
        // eslint-disable-next-line react-hooks/refs
        return new DefaultChatTransport({
            api: ConversationController.chat(conversation.id).url,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },

            prepareSendMessagesRequest: ({ messages }) => {
                const lastMessage = messages[messages.length - 1];

                const approvalParts = lastMessage.parts.filter(
                    (part) => {
                        const partTool = part as ToolUIPart

                        return (
                            partTool.type.startsWith('tool-') &&
                            partTool.state === 'approval-responded'
                        );
                    }
                )

                if (approvalParts.length > 0) {
                    return {
                        body: {
                            decisions: approvalParts.map((part) => {
                                const partTool = part as any;

                                return {
                                    approvalId: partTool.approval.id,
                                    approved: partTool.approval.approved,
                                    reason: partTool.approval.reason || '',
                                };
                            }),
                        },
                    };
                }

                let text = '';

                const files: FileUIPart[] = [];

                lastMessage.parts.forEach((part) => {
                    if (part.type === 'text') {
                        text += part.text;
                    }

                    if (part.type === 'file') {
                        files.push(part);
                    }
                });

                return {
                    body: {
                        message: text,
                        files: files,
                    },
                };
            },

            async fetch(input, init) {
                const payload = JSON.parse(init!.body as string);

                const formData = new FormData();

                if(payload.message) {
                    formData.append('message', payload.message);
                }

                if(payload.decisions && payload.decisions.length > 0) {
                    payload.decisions.forEach((decision: ToolApprovalResponse, index: number) => {
                        formData.append(`decisions[${index}][action]`, decision.approved ? 'approve' : 'reject');
                        formData.append(`decisions[${index}][id]`, decision.approvalId);

                        if(!decision.approved && decision.reason) {
                            formData.append(`decisions[${index}][result]`, decision.reason);
                        }
                    });
                }

                for (const file of payload.files ?? []) {
                    formData.append('files[]', await dataUrlToBlob(file));
                }

                const headers = new Headers(init!.headers);
                headers.delete('Content-Type');

                const response = await fetch(input, { ...init, headers, body: formData });

                if (response.body) {
                    const [forSdk, forSniffing] = response.body.tee();
                    sniffApprovalReasons(forSniffing);

                    return new Response(forSdk, {
                        status: response.status,
                        statusText: response.statusText,
                        headers: response.headers,
                    });
                }

                return response;
            },
        });
    }, []);

    const pendingMessage = useConversationStore((state) => state.pendingMessage);
    const clearPendingMessage = useConversationStore((state) => state.clearPendingMessage);

    const sentApprovalBatchesRef = useRef<Set<string>>(new Set());

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
        regenerate,
        addToolApprovalResponse,
        approvalReasonsRef,
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
