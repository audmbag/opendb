import { useHttp, usePage } from '@inertiajs/react'
import { useCallback } from 'react';
import { useConversationStore } from '@/store/use-conversation-store';
import type { APIData } from '@/types/global';
import type { Conversation } from '@/types/models/conversation';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';
import type { PromptInputMessage } from '@/components/ai-elements/prompt-input';

const useConversation = () => {

    const conversations = useConversationStore.use.conversations()
    const fillConversations = useConversationStore.use.setConversations();
    const addConversation = useConversationStore.use.addConversation();
    const page = usePage<{project: Project}>()

    const loadConversationsRequest = useHttp<null, APIData<Conversation>>();
    const startConversationRequest = useHttp<null, Conversation>();

    const loadConversations = useCallback(() => {
        loadConversationsRequest.get(ConversationController.index.url(page.props.project.id), {
            onSuccess: ({ data }) => {
                fillConversations(data);
            },
        });
    }, []);

    const startConversation = useCallback((message: PromptInputMessage) => {
        startConversationRequest.post(
            ConversationController.store.url(page.props.project.id, {
                query: { start: true },
            }),
            {
                onSuccess: (conversation) => {
                    addConversation(conversation);
                },
            },
        );
    }, []);



    return {
        conversations,
        startConversation,
        startConversationRequest,
        loadConversations,
        loadConversationsRequest,
    };
}




export { useConversation }
