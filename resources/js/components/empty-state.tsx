import type { LucideIcon } from 'lucide-react';
import React from 'react';
import type { ButtonProps } from '@/components/form/button';
import Button from '@/components/form/button';
import IconStackGlow from '@/components/icon-stack-glow';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';


export type EmptyStateAction = ButtonProps & {
    label: string;
}

type Props = {
    title?: string;
    description?: string;
    icon?: LucideIcon;
    actions?: EmptyStateAction[];
} & React.ComponentProps<typeof Empty>;

export function EmptyState({ title, description, icon: Icon, actions = [], ...props }: Props) {
    return (
        <Empty {...props}>
            <EmptyHeader>
                {Icon && (
                    <EmptyMedia variant="icon">
                        <IconStackGlow icon={Icon} className="pb-6" />
                    </EmptyMedia>
                )}
                {title && <EmptyTitle>{title}</EmptyTitle>}
                {description && (
                    <EmptyDescription>{description}</EmptyDescription>
                )}
            </EmptyHeader>
            {!!actions.length && (
                <EmptyContent className="flex-row justify-center gap-2">
                    {actions.map(({ label, ...props }, index) => (
                        <Button key={index} {...props}>
                            {label}
                        </Button>
                    ))}
                </EmptyContent>
            )}
        </Empty>
    );
}
