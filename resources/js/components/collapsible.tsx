import { ChevronRightIcon, SquareTerminalIcon } from 'lucide-react';
import React from 'react';
import { buttonVariants } from '@/components/ui/button';
import { CollapsibleContent, CollapsibleTrigger, Collapsible as UICollapsible } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
} & React.ComponentProps<typeof UICollapsible>;

export default function Collapsible({ label, children, className, ...props }: Props) {
    return (
        <UICollapsible {...props}>
            <CollapsibleTrigger
                className={buttonVariants({
                    variant: 'secondary',
                    className:
                        'group w-full justify-start group-data-[state=open]:rounded-b-none!',
                })}
            >
                <SquareTerminalIcon />
                {label}
                <ChevronRightIcon className="ml-auto size-4 transition-transform group-data-[state=open]:rotate-90" />
            </CollapsibleTrigger>
            <CollapsibleContent className={cn('p-2.5 pt-0', className)}>
                {children}
            </CollapsibleContent>
        </UICollapsible>
    );
}
