import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * The bar of the actions at the bottom of a modal, below its content.
 */
export default function ModalFooter({
    className,
    ...props
}: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-end gap-2.5 border-t border-border bg-accent p-4',
                className,
            )}
            {...props}
        />
    );
}
