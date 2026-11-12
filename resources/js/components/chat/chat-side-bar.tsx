import { router, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
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
    const { t } = useLaravelReactI18n();
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
        <SidebarGroupContent className="flex min-h-0 flex-1 flex-col">
            {conversations.length > 0 ? (
                <>
                    <SidebarGroupLabel>
                        {t('conversations.recent')}
                    </SidebarGroupLabel>
                    <SidebarMenu className="no-scrollbar min-h-0 flex-1 overflow-y-auto">
                        {conversations.map((conversation) => (
                            <SidebarMenuItem
                                key={conversation.id}
                                label={
                                    conversation.title ||
                                    t('conversations.generating_title')
                                }
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
                                        label: t('common.edit'),
                                        icon: PencilIcon,
                                        onClick: () =>
                                            setEditingConversation(
                                                conversation,
                                            ),
                                    },
                                    {
                                        label: t('conversations.archive'),
                                        icon: ArchiveIcon,
                                        separator: true,
                                        onClick: () => archive(conversation),
                                    },
                                    {
                                        label: t('common.delete'),
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
                    description={t('conversations.empty_history')}
                    className="p-0 pt-8"
                />
            ) : null}
        </SidebarGroupContent>
    );
};

export default function ChatSideBar() {
    const { t } = useLaravelReactI18n();
    const page = usePage<{ project: Project }>();

    const [searchOpen, setSearchOpen] = useState(false);

    const SideBarItems = useMemo<SidebarMenuItemType[]>(
        () => [
            {
                label: t('conversations.new'),
                icon: PlusCircleIcon,
                shortcut: ['Alt', 'N'],
                variant: 'outline',
                href: ConversationController.home(page.props.project.id).url,
                viewTransition: true,
            },
        ],
        [page.props.project.id, t],
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
                        tooltip={[t('conversations.search'), ['Alt', 'K']]}
                        onClick={() => setSearchOpen(true)}
                    />
                    <SidebarTrigger aria-label={t('common.toggle_sidebar')} />
                </SidebarHeader>
                <SidebarContent className="overflow-hidden">
                    <SidebarGroup className="shrink-0">
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
                    <SidebarGroup className="min-h-0 flex-1">
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
