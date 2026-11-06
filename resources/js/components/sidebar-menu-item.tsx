import { Link, usePage } from '@inertiajs/react';
import { ExternalLinkIcon, MoreHorizontalIcon } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import * as React from 'react';
import { Fragment, useCallback } from 'react';
import DropdownMenu from '@/components/dropdown-menu';
import type { DropdownMenuItemType } from '@/components/dropdown-menu';
import Button from '@/components/form/button';
import {
    Editable,
    EditableArea,
    EditableInput,
    EditablePreview,
} from '@/components/ui/editable';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { Separator } from '@/components/ui/separator';
import {
    SidebarMenuButton,
    SidebarMenuItem as UISidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { Skeleton } from '@/components/ui/skeleton';
import { cn, limit } from '@/lib/utils';

export type SidebarMenuItemType = {
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
    isActive?: boolean;
    viewTransition?: boolean;
    editing?: boolean;
    onSubmitEditing?: (value: string) => void;
    onCancelEditing?: () => void;
    /**
     * Shows a placeholder instead of the label while it is not known yet, like the title being generated.
     */
    loading?: boolean;
};

/**
 * The dropdown trigger lives inside the item's link: stop its click from navigating.
 */
const preventLinkNavigation = (event: React.MouseEvent) => {
    event.preventDefault();
    event.stopPropagation();
};

export const SidebarMenuItem = ({
    label,
    icon: Icon,
    separator,
    external,
    shortcut,
    variant,
    options = [],
    hiddenOnSidebarClosed = false,
    href,
    isActive,
    viewTransition,
    editing,
    onSubmitEditing,
    onCancelEditing,
    loading = false,
    onClick,
    ...pros
}: SidebarMenuItemType & React.ComponentProps<typeof Fragment>) => {
    const { open } = useSidebar();

    const { url } = usePage();

    const isCurrentPage =
        isActive ?? (!!href && !external && url.split('?')[0] === href);

    const handleEditing = useCallback(
        (value: string) => {
            if (value === label) {
                onCancelEditing?.();

                return;
            }

            onSubmitEditing?.(value);
        },
        [label, onCancelEditing, onSubmitEditing],
    );

    const content = (
        <>
            {Icon && <Icon />}
            {loading ? (
                <Skeleton className="h-3.5 w-full max-w-36" />
            ) : (
                <span>{limit(label, 20)}</span>
            )}
            {external && <ExternalLinkIcon className="ml-auto" />}
            {shortcut && (
                <KbdGroup className="ml-auto">
                    {shortcut.map((key) => (
                        <Kbd key={key} className="ml-auto">
                            {key}
                        </Kbd>
                    ))}
                </KbdGroup>
            )}
        </>
    );

    return (
        <Fragment {...pros}>
            {(!hiddenOnSidebarClosed || open) && (
                <>
                    <UISidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={label}
                            variant={variant}
                            isActive={isCurrentPage}
                            title={hiddenOnSidebarClosed ? label : undefined}
                            className={cn(
                                'text-nowrap data-active:bg-zinc-200 data-active:text-sidebar-foreground data-active:hover:bg-zinc-200 dark:data-active:bg-zinc-800',
                                {
                                    'justify-between': options.length > 0,
                                    'p-0': editing,
                                },
                            )}
                        >
                            {editing ? (
                                <Editable
                                    defaultValue={label}
                                    onSubmit={handleEditing}
                                    placeholder="Titre de la conversation"
                                    editing={editing}
                                >
                                    <EditableArea>
                                        <EditablePreview />
                                        <EditableInput
                                            className="px-1"
                                            autoComplete="off"
                                        />
                                    </EditableArea>
                                </Editable>
                            ) : onClick && !href ? (
                                <button type="button" onClick={onClick}>
                                    {content}
                                </button>
                            ) : (
                                <Link
                                    href={href}
                                    target={external ? '_blank' : undefined}
                                    viewTransition={viewTransition}
                                >
                                    {content}
                                    {options.length > 0 && (
                                        <DropdownMenu items={options}>
                                            <Button
                                                icon={MoreHorizontalIcon}
                                                variant="ghost"
                                                size="icon-sm"
                                                className="size-6 h-4 shrink-0"
                                                onClick={preventLinkNavigation}
                                            />
                                        </DropdownMenu>
                                    )}
                                </Link>
                            )}
                        </SidebarMenuButton>
                    </UISidebarMenuItem>
                    {separator && <Separator />}
                </>
            )}
        </Fragment>
    );
};

export default SidebarMenuItem;
