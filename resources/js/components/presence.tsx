import { Presence as PresencePrimitive } from "radix-ui/internal";
import React from 'react';
import { cn } from '@/lib/utils';

/**
 * Animates its content in and out, and unmounts it once the exit animation ends.
 * The child is passed as an element: Radix keeps a child given as a function always mounted.
 */
const Presence = ({ present: visible, children, className, ...props } : { present : boolean } & React.ComponentProps<"div">) => {
    return (
        <PresencePrimitive.Root present={visible}>
            <div
                className={cn(
                    'animate w-full fill-mode-forwards',
                    visible
                        ? 'animate-in fade-in-0'
                        : 'animate-out fade-out-0',
                    className,
                )}
                {...props}
            >
                {children}
            </div>
        </PresencePrimitive.Root>
    );
};

export default Presence
