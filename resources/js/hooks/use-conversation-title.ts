import { http } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useEffect, useState } from 'react';
import { useConversationStore } from '@/store/use-conversation-store';
import type { Conversation } from '@/types/models/conversation';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';

const POLL_INTERVAL = 1000;

/**
 * After this many attempts the title is given up on: the request that generates it probably died.
 */
const MAX_ATTEMPTS = 120;

type TitleListener = (title: string) => void;

type TitleWatch = {
    listeners: Set<TitleListener>;
    timer?: ReturnType<typeof setTimeout>;
};

/**
 * One reading of each conversation at a time, whoever waits for its title: the page and the sidebar both do.
 */
const titleWatches = new Map<string, TitleWatch>();

function stopWatching(id: string, watch: TitleWatch): void {
    clearTimeout(watch.timer);

    if (titleWatches.get(id) === watch) {
        titleWatches.delete(id);
    }
}

/**
 * Read the conversation again every second until it has a title, then write it into the list of the sidebar.
 */
function pollTitle(id: string, watch: TitleWatch, attempt: number): void {
    watch.timer = setTimeout(async () => {
        try {
            const response = await http.getClient().request({
                method: 'get',
                url: ConversationController.show.url(id),
                headers: { Accept: 'application/json' },
            });
            const generated = (
                JSON.parse(response.data) as { conversation: Conversation }
            ).conversation.title;

            if (generated && titleWatches.get(id) === watch) {
                stopWatching(id, watch);
                useConversationStore.getState().rename(id, generated);
                watch.listeners.forEach((listener) => listener(generated));

                return;
            }
        } catch {
            // A dropped request is tried again on the next tick: the connection may be slow.
        }

        if (titleWatches.get(id) !== watch) {
            return;
        }

        if (attempt < MAX_ATTEMPTS) {
            pollTitle(id, watch, attempt + 1);
        } else {
            stopWatching(id, watch);
            useConversationStore.getState().giveUpTitle(id);
        }
    }, POLL_INTERVAL);
}

/**
 * @returns a function to stop waiting: the reading stops once nobody waits anymore
 */
function watchTitle(id: string, listener: TitleListener): () => void {
    let watch = titleWatches.get(id);

    if (!watch) {
        watch = { listeners: new Set() };
        titleWatches.set(id, watch);
        pollTitle(id, watch, 1);
    }

    const current = watch;
    current.listeners.add(listener);

    return () => {
        current.listeners.delete(listener);

        if (current.listeners.size === 0) {
            stopWatching(id, current);
        }
    };
}

/**
 * The title of the conversation, null while it is being generated. A title that never comes, because the request
 * generating it died, gives way to a default one after two minutes instead of a placeholder shown forever.
 */
export function useConversationTitle(
    conversation: Conversation,
): string | null {
    const storedTitle = useConversationStore(
        (state) =>
            state.conversations.find((item) => item.id === conversation.id)
                ?.title,
    );
    const givenUp = useConversationStore((state) =>
        state.titlesGivenUp.includes(conversation.id),
    );
    const { t } = useLaravelReactI18n();
    const [fetchedTitle, setFetchedTitle] = useState<{
        id: string;
        title: string;
    } | null>(null);

    // The page may open before the list of the sidebar is loaded, or the list be loaded again before the title came.
    const title =
        storedTitle ||
        conversation.title ||
        (fetchedTitle?.id === conversation.id ? fetchedTitle.title : null);

    useEffect(() => {
        if (title || givenUp) {
            return;
        }

        return watchTitle(conversation.id, (generated) =>
            setFetchedTitle({ id: conversation.id, title: generated }),
        );
    }, [conversation.id, title, givenUp]);

    return title || (givenUp ? t('conversations.new_conversation') : null);
}
