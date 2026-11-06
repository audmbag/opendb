import type { BaseModel } from '@/types/models/base-model';


export interface ConversationMessage extends BaseModel {
    conversation_id: string;
    role: 'user' | 'agent';
    content: string;
    attachments: unknown[];
    steps: unknown[];
    status: 'completed' | 'paused' | 'failed';
    tool_calls: unknown[];
    tool_results: unknown[];
    provider_tool_calls: unknown[];
    usage: unknown[];
    meta: unknown[];
    is_start: boolean;
}

export interface Conversation extends BaseModel {
    title?: string;
    project_id: string;
    messages: ConversationMessage[];
}

export interface ConversationSearchResult extends Omit<Conversation, 'messages'> {
    /**
     * The passage around the search in the first message containing it, null when only the title matches.
     */
    excerpt: string | null;
}
