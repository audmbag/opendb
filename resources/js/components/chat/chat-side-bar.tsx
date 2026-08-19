import { usePage } from '@inertiajs/react';
import {
    ArchiveIcon,
    Loader,
    MessageSquareTextIcon,
    PencilIcon,
    PlusCircleIcon,
    SearchIcon,
    TrashIcon,
} from 'lucide-react';
import React, { useEffect, useMemo } from 'react';
import type { SidebarItemType } from '@/components/app-sidebar';
import { MenuItem } from '@/components/app-sidebar';
import type { DropdownMenuItemType } from '@/components/dropdown-menu';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
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
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';


type ConversationMenuItem = {
    id: number;
    title: string;
    options?: DropdownMenuItemType[];
}


const Conversations = () => {

    const { open } = useSidebar();

    const { conversations, loadConversations, loadConversationsRequest } = useConversation();

    useEffect(() => {
        loadConversations();
    }, [loadConversations]);

    if (loadConversationsRequest.processing) {
        return <SidebarGroupContent>
            <div className="w-full flex justify-center">
                <Loader className="size-4 animate-spin" />
            </div>
        </SidebarGroupContent>
    }

    return (
        <SidebarGroupContent>
            {conversations.length > 0 ? (
                <>
                    <SidebarGroupLabel>Récents</SidebarGroupLabel>
                    <SidebarMenu>
                        {conversations.map((prompt) => (
                            <MenuItem
                                key={prompt.id}
                                label={prompt.title}
                                hiddenOnSidebarClosed
                                options={[
                                    {
                                        label: 'Éditer',
                                        icon: PencilIcon,
                                    },
                                    {
                                        label: 'Archiver',
                                        icon: ArchiveIcon,
                                        separator: true,
                                    },
                                    {
                                        label: 'Supprimer',
                                        icon: TrashIcon,
                                        destructive: true,
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
}

export default function ChatSideBar() {

    const page = usePage<{project: Project}>()


    const SideBarItems = useMemo<SidebarItemType[]>(() => [
        {
            label: 'Nouvelle discussion',
            icon: PlusCircleIcon,
            shortcut: ['Ctrl', 'N'],
            variant: 'outline',
            href: ConversationController.home(page.props.project.id).url,
            viewTransition: true
        },
    ], [page.props.project.id]);


    return (
        <Sidebar
            className="absolute hidden h-full flex-1 overflow-hidden md:flex"
            collapsible={'icon'}
        >
            <SidebarHeader className="flex flex-row flex-wrap justify-between gap-2">
                <Button icon={SearchIcon} variant="secondary" size="icon-sm" />
                <SidebarTrigger />
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {SideBarItems.map((item) => (
                                <MenuItem key={item.label} {...item} />
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
                <SidebarGroup>
                    <Conversations />
                </SidebarGroup>
            </SidebarContent>
        </Sidebar>
    );
}
