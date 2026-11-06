import { http } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useConversationStore } from '@/store/use-conversation-store';
import type { Conversation } from '@/types/models/conversation';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';

const POLL_INTERVAL = 1000;

/**
 * After this many attempts the title is given up on: the queue worker is probably not running.
 */
const MAX_ATTEMPTS = 120;

const FALLBACK_TITLE = 'Nouvelle conversation';

/**
 * The title of the conversation, null while the queue is still generating it. Until then, the conversation is read
 * again every second, and the title is written into the list of the sidebar as soon as it is ready.
 */
export function useConversationTitle(conversation: Conversation): string | null {
    const storedTitle = useConversationStore(
        (state) => state.conversations.find((item) => item.id === conversation.id)?.title,
    );
    const rename = useConversationStore((state) => state.rename);
    const [fetchedTitle, setFetchedTitle] = useState<{ id: string; title: string } | null>(null);
    const [givenUpId, setGivenUpId] = useState<string | null>(null);

    const title =
        storedTitle ||
        conversation.title ||
        (fetchedTitle?.id === conversation.id ? fetchedTitle.title : null);

    useEffect(() => {
        if (title) {
            return;
        }

        let attempts = 0;
        let cancelled = false;
        let timer: ReturnType<typeof setTimeout>;

        const poll = async () => {
            attempts++;

            try {
                const response = await http.getClient().request({
                    method: 'get',
                    url: ConversationController.show.url(conversation.id),
                    headers: { Accept: 'application/json' },
                });
                const generated = (JSON.parse(response.data) as { conversation: Conversation }).conversation.title;

                if (generated && !cancelled) {
                    setFetchedTitle({ id: conversation.id, title: generated });
                    rename(conversation.id, generated);

                    return;
                }
            } catch {
                // A dropped request is tried again on the next tick: the connection may be slow.
            }

            if (cancelled) {
                return;
            }

            if (attempts < MAX_ATTEMPTS) {
                timer = setTimeout(poll, POLL_INTERVAL);
            } else {
                setGivenUpId(conversation.id);
            }
        };

        timer = setTimeout(poll, POLL_INTERVAL);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [conversation.id, title, rename]);

    return title ?? (givenUpId === conversation.id ? FALLBACK_TITLE : null);
}
