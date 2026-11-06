import { router, useHttp, usePage } from '@inertiajs/react';
import { MessageSquareTextIcon, SearchXIcon } from 'lucide-react';
import React, { Fragment, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { Frame, FramePanel } from '@/components/reui/frame';
import {
    Command,
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Spinner } from '@/components/ui/spinner';
import { useConversationStore } from '@/store/use-conversation-store';
import type { APIData } from '@/types/global';
import type { ConversationSearchResult } from '@/types/models/conversation';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';

const SEARCH_DELAY = 250;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type SearchResults = {
    search: string;
    conversations: ConversationSearchResult[];
};

const escapeRegExp = (value: string): string =>
    value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * Put the occurrences of the search in bold, ignoring the case.
 */
const Highlight = ({ text, search }: { text: string; search: string }) => {
    if (!search) {
        return <>{text}</>;
    }

    const normalizedSearch = search.toLowerCase();

    return (
        <>
            {text
                .split(new RegExp(`(${escapeRegExp(search)})`, 'gi'))
                .map((part, index) =>
                    part.toLowerCase() === normalizedSearch ? (
                        <mark
                            key={index}
                            className="bg-transparent font-semibold text-foreground"
                        >
                            {part}
                        </mark>
                    ) : (
                        <Fragment key={index}>{part}</Fragment>
                    ),
                )}
        </>
    );
};

const SearchConversationContent = ({
    onOpenChange,
}: Pick<Props, 'onOpenChange'>) => {
    const {
        props: { project },
    } = usePage<{ project: Project }>();

    const recentConversations = useConversationStore(
        (state) => state.conversations,
    );

    const [search, setSearch] = useState('');

    const [results, setResults] = useState<SearchResults | null>(null);

    const searchRequest = useHttp<null, APIData<ConversationSearchResult>>();

    const trimmedSearch = search.trim();

    useEffect(() => {
        if (!trimmedSearch) {
            return;
        }

        /**
         * An answer arriving after the user typed something else is ignored, so the list always matches the input.
         */
        let isCurrentSearch = true;

        const timeout = setTimeout(() => {
            searchRequest.get(
                ConversationController.search.url(project.id, {
                    query: { search: trimmedSearch },
                }),
                {
                    onSuccess: ({ data }) => {
                        if (isCurrentSearch) {
                            setResults({
                                search: trimmedSearch,
                                conversations: data,
                            });
                        }
                    },
                    onError: () => {
                        if (isCurrentSearch) {
                            setResults({
                                search: trimmedSearch,
                                conversations: [],
                            });
                            toast.error('La recherche a échoué, réessayez.');
                        }
                    },
                },
            );
        }, SEARCH_DELAY);

        return () => {
            isCurrentSearch = false;
            clearTimeout(timeout);
        };
    }, [trimmedSearch, project.id]);

    const isSearching =
        trimmedSearch !== '' && results?.search !== trimmedSearch;

    const conversations: ConversationSearchResult[] = trimmedSearch
        ? isSearching
            ? []
            : (results?.conversations ?? [])
        : recentConversations.map((conversation) => ({
              ...conversation,
              excerpt: null,
          }));

    const openConversation = (conversation: ConversationSearchResult) => {
        onOpenChange(false);
        router.visit(
            ConversationController.conversation.url({
                project: conversation.project_id,
                conversation: conversation.id,
            }),
            { viewTransition: true },
        );
    };

    return (
        <Command className="p-0" shouldFilter={false}>
            <Frame className="w-full">
                <FramePanel className="p-0">
                    <CommandInput
                        placeholder="Rechercher une conversation..."
                        value={search}
                        onValueChange={setSearch}
                    />
                    <CommandList className="max-h-96">
                        {isSearching ? (
                            <div className="flex justify-center py-6">
                                <Spinner />
                            </div>
                        ) : (
                            <CommandEmpty className="py-0">
                                <EmptyState
                                    icon={
                                        trimmedSearch
                                            ? SearchXIcon
                                            : MessageSquareTextIcon
                                    }
                                    description={
                                        trimmedSearch
                                            ? 'Aucune conversation ne correspond à votre recherche'
                                            : "Vos historiques de conversations s'afficheront ici"
                                    }
                                    className="py-8"
                                />
                            </CommandEmpty>
                        )}
                        {conversations.length > 0 && (
                            <CommandGroup
                                heading={
                                    trimmedSearch ? 'Résultats' : 'Récents'
                                }
                            >
                                {conversations.map((conversation) => (
                                    <CommandItem
                                        key={conversation.id}
                                        value={conversation.id}
                                        onSelect={() =>
                                            openConversation(conversation)
                                        }
                                        className="items-start"
                                    >
                                        <MessageSquareTextIcon className="mt-0.5 text-muted-foreground" />
                                        <div className="flex min-w-0 flex-col gap-0.5">
                                            <span className="truncate">
                                                <Highlight
                                                    text={
                                                        conversation.title ||
                                                        '...'
                                                    }
                                                    search={trimmedSearch}
                                                />
                                            </span>
                                            {conversation.excerpt && (
                                                <span className="line-clamp-2 text-xs text-muted-foreground">
                                                    <Highlight
                                                        text={
                                                            conversation.excerpt
                                                        }
                                                        search={trimmedSearch}
                                                    />
                                                </span>
                                            )}
                                        </div>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        )}
                    </CommandList>
                </FramePanel>
            </Frame>
        </Command>
    );
};

export default function SearchConversationModal({ open, onOpenChange }: Props) {
    return (
        <CommandDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Rechercher une conversation"
            description="Recherchez dans les titres et les messages de vos conversations"
            className="sm:max-w-xl"
        >
            <SearchConversationContent onOpenChange={onOpenChange} />
        </CommandDialog>
    );
}
