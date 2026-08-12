import React from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon?: string,
    fill?: boolean,
    alt?: string
} & React.ComponentProps<'div'>;

export default function DatabaseIconHelper({ icon, fill, className, ...props }: Props) {
    return (
        <div
            className={cn(
                'size-6',
                fill
                    ? 'mr-0.5 bg-card rounded p-1 border border-border'
                    : '',
                className,
            )}
            {...props}
        >
            <img
                src={icon}
                alt={props.alt || 'Database Icon'}
                className="h-full w-full"
            />
        </div>
    );
}
