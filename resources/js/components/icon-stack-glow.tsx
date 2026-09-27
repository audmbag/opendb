import type { LucideIcon } from 'lucide-react';
import React from 'react';
import { IconTile } from '@/components/reui/icon-tile';

type Props = {
    icon: LucideIcon | React.ReactElement;
} & React.ComponentProps<typeof IconTile>;

export default function IconStackGlow({ icon: Icon, variant = "frame", ...props }: Props) {
    return (
        <IconTile {...props} variant={variant}>
            {React.isValidElement(Icon) ? (
                Icon
            ) : (
                <Icon />
            )}
        </IconTile>
    );
}
