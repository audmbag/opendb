import { usePage } from '@inertiajs/react';
import { FolderPlusIcon } from 'lucide-react';
import { FileTextIcon } from 'lucide-react';
import {
    Code2Icon,
    FolderCogIcon,
    GitCompareArrowsIcon,
    MessageSquareTextIcon,
    Table2Icon,
} from 'lucide-react';
import * as React from 'react';
import { SidebarMenuItem, SidebarMenuItemType } from '@/components/sidebar-menu-item';
import { SidebarUser } from '@/components/sidebar-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem as UISidebarMenuItem,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn, limit } from '@/lib/utils';
import type { Project } from '@/types/models/project';


const data: SidebarMenuItemType[] = [
    {
        label: 'Nouveau',
        icon: FolderPlusIcon,
        separator: true,
        shortcut: ['Ctrl', 'Shift', 'N'],
    },
    {
        label: 'Chat',
        icon: MessageSquareTextIcon,
    },
    {
        label: 'Tables',
        icon: Table2Icon,
    },
    {
        label: 'Editeur SQL',
        icon: Code2Icon,
    },
    {
        label: 'Schéma ERD',
        icon: GitCompareArrowsIcon,
        separator: true,
    },
    {
        label: 'Paramètres du projet',
        icon: FolderCogIcon,
    },
];

const Footerdata: SidebarMenuItemType[] = [
    {
        label: 'Documentation',
        icon: FileTextIcon,
        separator: true,
        external: true,
    }
];


const Header = () => {

    const { props: { project } } = usePage<{ project: Project }>();

    const { open } = useSidebar();

    return (
        <SidebarHeader>
            <SidebarMenu>
                <UISidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        asChild
                    >
                        <div>
                            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                {project.name.charAt(0).toUpperCase()}
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="line-clamp-1 truncate font-medium">
                                    {project.name}
                                </span>
                                <span className="truncate text-xs">
                                    {limit(project.description, 15)}
                                </span>
                            </div>
                            <SidebarTrigger
                                className={cn(
                                    'ml-auto rotate-180 transition-transform delay-200 duration-200',
                                    {
                                        'h-8 rotate-0': open,
                                    },
                                )}
                            />
                        </div>
                    </SidebarMenuButton>
                </UISidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
    );
};

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { open } = useSidebar();

    return (
        <Sidebar {...props} collapsible="icon" variant={'floating'}>
            <Header />
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarTrigger
                                className={cn(
                                    'hidden overflow-hidden transition-transform delay-200 duration-200 md:flex',
                                    {
                                        'h-0': open,
                                        'h-8 rotate-180': !open,
                                    },
                                )}
                            />
                            {data.map((item) => (
                                <SidebarMenuItem key={item.label} {...item} />
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter>
                <SidebarMenu>
                    {Footerdata.map((item) => (
                        <SidebarMenuItem key={item.label} {...item} />
                    ))}
                </SidebarMenu>
                <SidebarUser />
            </SidebarFooter>
        </Sidebar>
    );
}
