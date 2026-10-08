import React from 'react';
import IconStackGlow from '@/components/icon-stack-glow';

type Props = {
    icon?: string,
    fill?: boolean,
    alt?: string
} & React.ComponentProps<'img'>;

export default function DatabaseIconHelper({ icon, className, ...props }: Props) {
    return (
        <IconStackGlow
            className={className}
            icon={<img src={icon} alt={props.alt || 'Database Icon'} className="size-4" />} />
    );
}
