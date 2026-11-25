import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CommandIcon,
    FileTextIcon,
    FolderCogIcon,
    FolderPlusIcon,
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
import { useCommandModal } from '@/hooks/use-command-modal';
import { usePrivileges } from '@/hooks/use-privileges';
import { useCreateProject } from '@/hooks/use-project-modal';
import { useProjectNavigation } from '@/hooks/use-project-navigation';
import {
    useProjectSettingsDialog,
    useProjectSettingsNavItems,
} from '@/hooks/use-project-settings-modal';
import { DOCUMENTATION_URL } from '@/lib/links';
import { cn, limit } from '@/lib/utils';
import type { Project } from '@/types/models/project';

const footerItems = (
    t: (key: string) => string,
    openCommands: () => void,
): SidebarMenuItemType[] => [
    {
        label: t('commands.button'),
        icon: CommandIcon,
        shortcut: ['Ctrl', 'K'],
        onClick: openCommands,
    },
    {
        label: t('projects.menu.documentation'),
        icon: FileTextIcon,
        separator: true,
        external: true,
        href: DOCUMENTATION_URL,
    },
];

const Header = () => {
    const { t } = useLaravelReactI18n();
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
                                aria-label={t('common.toggle_sidebar')}
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

    const { open: openProjectSettings } = useProjectSettingsDialog();
    const { toggle: toggleCommands } = useCommandModal();
    const createProject = useCreateProject();
    const projectPages = useProjectNavigation();
    const projectSettingsPages = useProjectSettingsNavItems();
    const { can } = usePrivileges();
    const { t } = useLaravelReactI18n();
    const canCreateProject = can('create_project');
    const hasProjectSettings = projectSettingsPages.length > 0;

    const menuItems = React.useMemo<SidebarMenuItemType[]>(
        () => [
            ...(canCreateProject
                ? [
                      {
                          label: t('projects.menu.new'),
                          icon: FolderPlusIcon,
                          separator: true,
                          shortcut: ['Ctrl', 'Shift', 'N'],
                          onClick: createProject,
                      },
                  ]
                : []),
            ...projectPages.map((page, index) => ({
                ...page,
                separator:
                    index === projectPages.length - 1 && hasProjectSettings,
            })),
            ...(hasProjectSettings
                ? [
                      {
                          label: t('projects.menu.settings'),
                          icon: FolderCogIcon,
                          onClick: () => openProjectSettings(),
                      },
                  ]
                : []),
        ],
        [
            canCreateProject,
            createProject,
            hasProjectSettings,
            openProjectSettings,
            projectPages,
            t,
        ],
    );

    return (
        <Sidebar {...props} collapsible="icon" variant={'floating'}>
            <Header />
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarTrigger
                                aria-label={t('common.toggle_sidebar')}
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
                    {footerItems(t, () => toggleCommands()).map((item) => (
                        <SidebarMenuItem key={item.label} {...item} />
                    ))}
                </SidebarMenu>
                <SidebarUser />
            </SidebarFooter>
        </Sidebar>
    );
}
