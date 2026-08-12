import type { LucideIcon, LucideProps } from 'lucide-react';
import React from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
} & LucideProps & React.ComponentProps<'div'>;

export default function IconStackGlow({ icon: Icon, className, size, absoluteStrokeWidth, ...props }: Props) {
    return (
        <div
            className={cn('shrink-0', className)}
            {...props}
        >
            <div
                className='relative h-16 w-16'
            >
                <div className="absolute inset-0 translate-x-2 -translate-y-2 rounded-2xl border border-border/40"></div>
                <div className="absolute inset-0 translate-x-1 -translate-y-1 rounded-2xl border border-border/70"></div>
                <div className="icon-glow absolute inset-0 flex items-center justify-center rounded-2xl border border-border bg-card transition-colors">
                    {
                        <Icon
                            size={size}
                            absoluteStrokeWidth={absoluteStrokeWidth}
                        />
                    }
                </div>
            </div>
        </div>
    );
}
