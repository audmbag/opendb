import { create } from 'zustand';
import { devtools } from 'zustand/middleware';
import type { PromptInputMessage } from '@/components/ai-elements/prompt-input';
import { createSelectors } from '@/lib/utils';
import type { Conversation } from '@/types/models/conversation';

interface ConversationStore {
    conversations: Conversation[];
    pendingMessage: PromptInputMessage | null;
    /**
     * The conversations whose title was waited for in vain: they show a default title instead of a placeholder.
     */
    titlesGivenUp: string[];
    fill: (conversations: Conversation[]) => void;
    add: (conversation: Conversation) => void;
    remove: (conversation: Conversation) => void;
    rename: (id: string, title: string) => void;
    giveUpTitle: (id: string) => void;
    setPendingMessage: (message: PromptInputMessage | null) => void;
    clearPendingMessage: () => void;
}

const useConversationStore = createSelectors(
    create<ConversationStore>()(
        devtools(
            (set) => ({
                conversations: [],
                pendingMessage: null,
                titlesGivenUp: [],
                fill: (conversations) => set({ conversations: conversations }),
                add: (conversation: Conversation) =>
                    set((state) => ({
                        conversations: [conversation, ...state.conversations],
                    })),
                remove: (conversation: Conversation) =>
                    set((state) => ({
                        conversations: state.conversations.filter(
                            (c) => c.id !== conversation.id,
                        ),
                    })),
                rename: (id: string, title: string) =>
                    set((state) => ({
                        conversations: state.conversations.map((c) =>
                            c.id === id ? { ...c, title } : c,
                        ),
                    })),
                giveUpTitle: (id: string) =>
                    set((state) => ({
                        titlesGivenUp: [...state.titlesGivenUp, id],
                    })),
                setPendingMessage: (message: PromptInputMessage | null) =>
                    set({ pendingMessage: message }),
                clearPendingMessage: () => set({ pendingMessage: null }),
            }),
            { name: 'useConversationStore' },
        ),
    ),
);

export { useConversationStore };
