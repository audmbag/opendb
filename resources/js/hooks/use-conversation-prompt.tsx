import { createContext, useContext } from 'react';

type ConversationPromptContextType = {
    /**
     * Send a message to the agent as if the user had typed it, or null while the agent is answering.
     */
    sendPrompt: ((text: string) => void) | null;
};

const ConversationPromptContext = createContext<ConversationPromptContextType>({
    sendPrompt: null,
});

/**
 * Lets an artifact rendered in the conversation ask the agent a question (« Proposer un index »).
 */
export const ConversationPromptProvider = ConversationPromptContext.Provider;

export function useConversationPrompt(): ConversationPromptContextType {
    return useContext(ConversationPromptContext);
}
