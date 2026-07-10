import { ArrowUpRightIcon, FolderOpenIcon, LucideIcon } from 'lucide-react';

import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import React from 'react';
import Button, { ButtonProps } from '@/components/form/button';


export type EmptyStateAction = ButtonProps & {
    label: string;
} 

type Props = {
    title?: string;
    description?: string;
    icon?: LucideIcon | ((props: React.SVGProps<SVGSVGElement>) => React.ReactElement),
    actions?: EmptyStateAction[];
}

export function EmptyState({ title, description, icon: Icon, actions = [] }: Props) {
    return (
        <Empty>
            <EmptyHeader>
                {Icon && (
                    <EmptyMedia variant="icon">
                        <Icon />
                    </EmptyMedia>
                )}
                {title && (
                    <EmptyTitle>{title}</EmptyTitle>
                )}
                {description && (
                    <EmptyDescription>
                        {description}
                    </EmptyDescription>
                )}
            </EmptyHeader>
            {!!actions.length && <EmptyContent className="flex-row justify-center gap-2">
                {actions.map(({label, ...props}, index) => (
                    <Button key={index} {...props}>
                        {label}
                    </Button>
                ))}
            </EmptyContent>}
        </Empty>
    );
}
