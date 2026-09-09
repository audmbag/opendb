import type { LucideIcon} from 'lucide-react';
import React, { Fragment } from 'react';
import * as DropdownMenuUI from '@/components/ui/dropdown-menu';
import { DropdownMenuSeparator } from '@/components/ui/dropdown-menu';

export type DropdownMenuItemType = {
    label: string;
    icon?: LucideIcon;
    onClick?: () => void;
    separator?: boolean;
    destructive?: boolean;
};

type Props = {
    items: DropdownMenuItemType[];
} & React.ComponentProps<typeof DropdownMenuUI.DropdownMenu>;

export default function DropdownMenu({ items, children }: Props) {
    return (
        <DropdownMenuUI.DropdownMenu>
            <DropdownMenuUI.DropdownMenuTrigger asChild>
                {children}
            </DropdownMenuUI.DropdownMenuTrigger>
            <DropdownMenuUI.DropdownMenuContent>
                {items.map((item, index) => (
                    <Fragment key={index}>
                        <DropdownMenuUI.DropdownMenuItem
                            onClick={e => {
                                e.stopPropagation();
                                item.onClick?.()
                            }}
                            variant={item.destructive ? "destructive" : "default"}>
                            {item.icon && <item.icon />}
                            {item.label}
                        </DropdownMenuUI.DropdownMenuItem>
                        {item.separator && <DropdownMenuSeparator />}
                    </Fragment>
                ))}
            </DropdownMenuUI.DropdownMenuContent>
        </DropdownMenuUI.DropdownMenu>
    );
}
