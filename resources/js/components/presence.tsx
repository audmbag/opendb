import { Presence as PresencePrimitive } from "radix-ui/internal";
import React from 'react';
import { cn } from '@/lib/utils';

const Presence = ({ present: visible, children, className, ...props } : { present : boolean } & React.ComponentProps<"div">) => {
    return (
        <PresencePrimitive.Root present={visible}>
            {({ present }) => (
                <div
                    className={cn(
                        'animate w-full fill-mode-forwards',
                        present
                            ? 'animate-in fade-in-0'
                            : 'animate-out fade-out-0',
                        className,
                    )}
                    {...props}
                >
                    {children}
                </div>
            )}
        </PresencePrimitive.Root>
    );
};

export default Presence
