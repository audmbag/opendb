import { useHttp } from '@inertiajs/react'
import { useCallback } from 'react';
import { useConversationStore } from '@/store/use-conversation-store';
import type { APIData } from '@/types/global';
import type { Conversation } from '@/types/models/conversation';

const useConversation = () => {

    const conversations = useConversationStore.use.conversations()
    const fillConversations = useConversationStore.use.setConversations();

    const loadConversationsRequest = useHttp<null, APIData<Conversation>>();

    const loadConversations = useCallback(() => {
        loadConversationsRequest.get()
    }, [conversations]);

    const start = useCallback(() => {

    }, [])



    return {
        conversations,
        start,
        loadConversations,
    };
}




export { useConversation }
