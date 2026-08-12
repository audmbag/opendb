import type { LucideIcon } from 'lucide-react';
import React from 'react';
import Tooltip from '@/components/tooltip';
import type { TooltipProps } from '@/components/tooltip';
import { buttonVariants } from '@/components/ui/button';
import {
    Select as UISelect,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';


export interface SelectItemInterface {
    label: string;
    value: string;
    icon?: LucideIcon;
    description?: string;
    descriptionTooltip?: boolean;
}


type Props = {
    label?: string;
    items: SelectItemInterface[];
    button?: boolean;
    tooltip?: TooltipProps['value'];
    align?: React.ComponentProps<typeof SelectContent>['align'];
} & React.ComponentProps<typeof UISelect>;


export default function Select({ label, items, button, tooltip, align = "center", ...props }: Props) {
    return (
        <UISelect {...props}>
            <Tooltip value={tooltip}>
                <SelectTrigger
                    className={cn(
                        '[&_.description]:hidden!',
                        button &&
                            buttonVariants({ variant: 'ghost', size: 'sm' }),
                        button && 'justify-between shadow-none',
                    )}
                >
                    <SelectValue
                        placeholder={label || 'Séléctionner une option'}
                    />
                </SelectTrigger>
            </Tooltip>
            <SelectContent position="popper" align={align}>
                <SelectGroup>
                    {label && <SelectLabel>{label}</SelectLabel>}
                    {items.map((item) => (
                        <Tooltip
                            value={
                                item.descriptionTooltip
                                    ? item.description
                                    : undefined
                            }
                            key={item.value}
                        >
                            <SelectItem key={item.value} value={item.value}>
                                <div className={'flex flex-col gap-1'}>
                                    <p className={'flex items-center gap-1'}>
                                        {item.icon && (
                                            <item.icon className="h-4 w-4" />
                                        )}
                                        {item.label}
                                    </p>
                                    {item.description &&
                                        !item.descriptionTooltip && (
                                            <p className="description text-sm text-muted-foreground">
                                                {item.description}
                                            </p>
                                        )}
                                </div>
                            </SelectItem>
                        </Tooltip>
                    ))}
                </SelectGroup>
            </SelectContent>
        </UISelect>
    );
}
