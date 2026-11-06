import { router, usePage } from '@inertiajs/react';
import {
    ArchiveIcon,
    Loader,
    MessageSquareTextIcon,
    PencilIcon,
    PlusCircleIcon,
    SearchIcon,
    TrashIcon,
} from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useHotkeys } from 'react-hotkeys-hook';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import SearchConversationModal from '@/components/modals/search-conversation-modal';
import type { SidebarMenuItemType } from '@/components/sidebar-menu-item';
import SidebarMenuItem from '@/components/sidebar-menu-item';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { useConversation } from '@/hooks/use-conversation';
import { useConversationStore } from '@/store/use-conversation-store';
import type { Conversation } from '@/types/models/conversation';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';

const Conversations = () => {
    const { open } = useSidebar();

    const { loadConversations, loadConversationsRequest, archive, remove } =
        useConversation();

    const conversations = useConversationStore((state) => state.conversations);

    const [editingConversation, setEditingConversation] =
        useState<null | Conversation>(null);

    const { update } = useConversation();

    useEffect(() => {
        loadConversations();
    }, [loadConversations]);

    const handleUpdateTitle = useCallback(
        (conversation: Conversation, value: string) => {
            setEditingConversation(null);
            update(conversation, { title: value });
        },
        [update],
    );

    if (loadConversationsRequest.processing) {
        return (
            <SidebarGroupContent>
                <div className="flex w-full justify-center">
                    <Loader className="size-4 animate-spin" />
                </div>
            </SidebarGroupContent>
        );
    }

    return (
        <SidebarGroupContent>
            {conversations.length > 0 ? (
                <>
                    <SidebarGroupLabel>Récents</SidebarGroupLabel>
                    <SidebarMenu>
                        {conversations.map((conversation) => (
                            <SidebarMenuItem
                                key={conversation.id}
                                label={conversation.title || 'Génération du titre…'}
                                loading={!conversation.title}
                                hiddenOnSidebarClosed
                                viewTransition
                                editing={
                                    editingConversation?.id === conversation.id
                                }
                                onSubmitEditing={(value) =>
                                    handleUpdateTitle(conversation, value)
                                }
                                onCancelEditing={() =>
                                    setEditingConversation(null)
                                }
                                href={ConversationController.conversation.url({
                                    project: conversation.project_id,
                                    conversation: conversation.id,
                                })}
                                options={[
                                    {
                                        label: 'Éditer',
                                        icon: PencilIcon,
                                        onClick: () =>
                                            setEditingConversation(
                                                conversation,
                                            ),
                                    },
                                    {
                                        label: 'Archiver',
                                        icon: ArchiveIcon,
                                        separator: true,
                                        onClick: () => archive(conversation),
                                    },
                                    {
                                        label: 'Supprimer',
                                        icon: TrashIcon,
                                        destructive: true,
                                        onClick: () => remove(conversation),
                                    },
                                ]}
                            />
                        ))}
                    </SidebarMenu>
                </>
            ) : open ? (
                <EmptyState
                    icon={MessageSquareTextIcon}
                    description="Vos historiques de conversations d'afficherons ici"
                    className="p-0 pt-8"
                />
            ) : null}
        </SidebarGroupContent>
    );
};

export default function ChatSideBar() {
    const page = usePage<{ project: Project }>();

    const [searchOpen, setSearchOpen] = useState(false);

    const SideBarItems = useMemo<SidebarMenuItemType[]>(
        () => [
            {
                label: 'Nouvelle discussion',
                icon: PlusCircleIcon,
                shortcut: ['Alt', 'N'],
                variant: 'outline',
                href: ConversationController.home(page.props.project.id).url,
                viewTransition: true,
            },
        ],
        [page.props.project.id],
    );

    // Browsers keep Ctrl+N for themselves, whatever the page does: it only reaches windows without a browser interface.
    useHotkeys(
        ['alt+n', 'mod+n'],
        () =>
            router.visit(
                ConversationController.home(page.props.project.id).url,
                { viewTransition: true },
            ),
        { enableOnFormTags: true, preventDefault: true },
        [page.props.project.id],
    );

    useHotkeys('alt+k', () => setSearchOpen((open) => !open), {
        enableOnFormTags: true,
        preventDefault: true,
    });

    return (
        <>
            <Sidebar
                className="absolute hidden h-full flex-1 overflow-hidden md:flex"
                collapsible={'icon'}
            >
                <SidebarHeader className="flex flex-row flex-wrap justify-between gap-2">
                    <Button
                        icon={SearchIcon}
                        variant="secondary"
                        size="icon-sm"
                        tooltip={['Rechercher une conversation', ['Alt', 'K']]}
                        onClick={() => setSearchOpen(true)}
                    />
                    <SidebarTrigger />
                </SidebarHeader>
                <SidebarContent>
                    <SidebarGroup>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {SideBarItems.map((item) => (
                                    <SidebarMenuItem
                                        key={item.label}
                                        {...item}
                                    />
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                    <SidebarGroup>
                        <Conversations />
                    </SidebarGroup>
                </SidebarContent>
            </Sidebar>
            <SearchConversationModal
                open={searchOpen}
                onOpenChange={setSearchOpen}
            />
        </>
    );
}
