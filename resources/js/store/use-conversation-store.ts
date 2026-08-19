import { create } from 'zustand';
import { devtools } from 'zustand/middleware';
import { createSelectors } from '@/lib/utils';
import type { Conversation } from '@/types/models/conversation';

interface ConversationStore {
    conversations: Conversation[];
    setConversations: (conversations: Conversation[]) => void;
    addConversation: (conversation: Conversation) => void;
}

const useConversationStore = createSelectors(
    create<ConversationStore>()(devtools((set) => ({
            conversations: [],
            setConversations: (conversations) => set({ conversations: conversations }),
            addConversation: (conversation: Conversation) => set(state => ({ conversations: [conversation, ...state.conversations] })),
        }), { name: 'useConversationStore' }),
    ));

export { useConversationStore };
