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
import type { SidebarMenuItemType } from '@/components/sidebar-menu-item';
import { SidebarMenuItem } from '@/components/sidebar-menu-item';
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
import { useProjectSettingsDialog } from '@/hooks/use-project-settings-modal';
import { cn, limit } from '@/lib/utils';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';
import SqlEditorController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlEditorController';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';

const getMenuItems = (
    project: Project,
    currentUrl: string,
    openProjectSettings: () => void,
): SidebarMenuItemType[] => {
    const chatUrl = ConversationController.home(project.id).url;
    const tablesUrl = TableBrowserController.index.url({ project: project.id });
    const sqlEditorUrl = SqlEditorController.index.url({ project: project.id });

    return [
        {
            label: 'Nouveau',
            icon: FolderPlusIcon,
            separator: true,
            shortcut: ['Ctrl', 'Shift', 'N'],
        },
        {
            label: 'Chat',
            icon: MessageSquareTextIcon,
            href: chatUrl,
            isActive:
                currentUrl === chatUrl ||
                currentUrl.startsWith(`${chatUrl}/chat/`),
        },
        {
            label: 'Tables',
            icon: Table2Icon,
            href: tablesUrl,
            isActive:
                currentUrl === tablesUrl ||
                currentUrl.startsWith(`${tablesUrl}/`),
        },
        {
            label: 'Éditeur SQL',
            icon: Code2Icon,
            href: sqlEditorUrl,
            isActive: currentUrl === sqlEditorUrl,
        },
        {
            label: 'Schéma ERD',
            icon: GitCompareArrowsIcon,
            separator: true,
        },
        {
            label: 'Paramètres du projet',
            icon: FolderCogIcon,
            onClick: openProjectSettings,
        },
    ];
};

const Footerdata: SidebarMenuItemType[] = [
    {
        label: 'Documentation',
        icon: FileTextIcon,
        separator: true,
        external: true,
    },
];

const Header = () => {
    const {
        props: { project },
    } = usePage<{ project: Project }>();

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

    const {
        url,
        props: { project },
    } = usePage<{ project: Project }>();

    const { open: openProjectSettings } = useProjectSettingsDialog();

    const menuItems = React.useMemo(
        () =>
            getMenuItems(project, url.split('?')[0], () =>
                openProjectSettings(),
            ),
        [openProjectSettings, project, url],
    );

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
                            {menuItems.map((item) => (
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
