import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { MoreHorizontalIcon } from 'lucide-react';
import { FolderPlusIcon } from 'lucide-react';
import { ExternalLinkIcon, FileTextIcon } from 'lucide-react';
import {
    Code2Icon,
    FolderCogIcon,
    GitCompareArrowsIcon,
    MessageSquareTextIcon,
    Table2Icon,
} from 'lucide-react';
import * as React from 'react';
import { Fragment } from 'react';
import type { DropdownMenuItemType } from '@/components/dropdown-menu';
import DropdownMenu from '@/components/dropdown-menu';
import Button from '@/components/form/button';
import { SidebarUser } from '@/components/sidebar-user';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { Separator } from '@/components/ui/separator';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn, limit } from '@/lib/utils';
import type { Project } from '@/types/models/project';

export type SidebarItemType = {
    label: string;
    icon?: LucideIcon;
    separator?: boolean;
    external?: boolean;
    shortcut?: string[];
    variant?: React.ComponentProps<typeof SidebarMenuButton>['variant'];
    options?: DropdownMenuItemType[];
    hiddenOnSidebarClosed?: boolean;
    onClick?: () => void;
    href?: string;
    viewTransition?: boolean;
};

const data: SidebarItemType[] = [
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

const Footerdata: SidebarItemType[] = [
    {
        label: 'Documentation',
        icon: FileTextIcon,
        separator: true,
        external: true,
    }
];

export const MenuItem = ({
    label,
    icon: Icon,
    separator,
    external,
    shortcut,
    variant,
    options = [],
    hiddenOnSidebarClosed = false,
    href,
    viewTransition,
    ...pros
}: SidebarItemType & { key: React.Key }) => {
    const { open } = useSidebar();

    return (
        <Fragment {...pros}>
            {(!hiddenOnSidebarClosed || open) && (
                <>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={label}
                            variant={variant}
                            className={cn('text-nowrap', {
                                'justify-between': options.length > 0,
                            })}
                        >
                            <Link
                                href={href}
                                target={external ? '_blank' : undefined}
                                viewTransition={viewTransition}
                            >
                                {Icon && <Icon />}
                                <span>{limit(label, 20)}</span>
                                {external && (
                                    <ExternalLinkIcon className="ml-auto" />
                                )}
                                {shortcut && (
                                    <KbdGroup className="ml-auto">
                                        {shortcut.map((key) => (
                                            <Kbd key={key} className="ml-auto">
                                                {key}
                                            </Kbd>
                                        ))}
                                    </KbdGroup>
                                )}
                                {options.length > 0 && (
                                    <DropdownMenu items={options}>
                                        <Button
                                            icon={MoreHorizontalIcon}
                                            variant="ghost"
                                            size="icon-sm"
                                            className="size-6 h-4"
                                        />
                                    </DropdownMenu>
                                )}
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    {separator && <Separator />}
                </>
            )}
        </Fragment>
    );
};

const Header = () => {

    const { props: { project } } = usePage<{ project: Project }>();

    const { open } = useSidebar();

    return (
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
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
                </SidebarMenuItem>
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
                                <MenuItem key={item.label} {...item} />
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter>
                <SidebarMenu>
                    {Footerdata.map((item) => (
                        <MenuItem key={item.label} {...item} />
                    ))}
                </SidebarMenu>
                <SidebarUser />
            </SidebarFooter>
        </Sidebar>
    );
}
