import { Link } from '@inertiajs/react';
import { ExternalLinkIcon,  MoreHorizontalIcon } from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import * as React from 'react';
import { Fragment, useCallback, useState } from 'react';
import DropdownMenu from '@/components/dropdown-menu';
import type {DropdownMenuItemType} from '@/components/dropdown-menu';
import Button from '@/components/form/button';
import Tooltip from '@/components/tooltip';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { Separator } from '@/components/ui/separator';
import {
    SidebarMenuButton,
    SidebarMenuItem as UISidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn, limit } from '@/lib/utils';
import {
    Editable,
    EditableArea,
    EditableInput,
    EditablePreview,
} from '@/components/ui/editable';

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
    viewTransition?: boolean;
    editing?: boolean;
    onSubmitEditing?: (value: string) => void;
    onCancelEditing?: () => void;
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
    viewTransition,
    editing,
    onSubmitEditing,
    onCancelEditing,
    ...pros
}: SidebarMenuItemType & React.ComponentProps<typeof Fragment>) => {
    const { open } = useSidebar();

    const handleEditing = useCallback((value: string) => {
        if(value === label) {
            onCancelEditing?.()

            return;
        }

        onSubmitEditing?.(value);
    }, [label, onCancelEditing, onSubmitEditing]);

    return (
        <Fragment {...pros}>
            {(!hiddenOnSidebarClosed || open) && (
                <>
                    <UISidebarMenuItem>
                        <Tooltip
                            value={hiddenOnSidebarClosed ? label : undefined}
                            side={'right'}
                        >
                            <SidebarMenuButton
                                asChild
                                tooltip={label}
                                variant={variant}
                                className={cn('text-nowrap', {
                                    'justify-between': options.length > 0,
                                    'p-0': editing,
                                })}
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
                                ) : (
                                    <Link
                                        href={href}
                                        target={external ? '_blank' : undefined}
                                        viewTransition={viewTransition}
                                    >
                                        {Icon && <Icon />}
                                        <span>{limit(label, 25)}</span>
                                        {external && (
                                            <ExternalLinkIcon className="ml-auto" />
                                        )}
                                        {shortcut && (
                                            <KbdGroup className="ml-auto">
                                                {shortcut.map((key) => (
                                                    <Kbd
                                                        key={key}
                                                        className="ml-auto"
                                                    >
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
                                )}
                            </SidebarMenuButton>
                        </Tooltip>
                    </UISidebarMenuItem>
                    {separator && <Separator />}
                </>
            )}
        </Fragment>
    );
};



export default SidebarMenuItem;
