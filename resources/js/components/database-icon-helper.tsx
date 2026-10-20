import React from 'react';
import IconStackGlow from '@/components/icon-stack-glow';

type Props = {
    icon?: string;
    fill?: boolean;
    alt?: string;
    forDriver?: string;
} & React.ComponentProps<'img'>;

export default function DatabaseIconHelper({ icon, className, forDriver, ...props }: Props) {

    if(forDriver) {
        return (
            <IconStackGlow
                className={className}
                icon={<img src={'/assets/images/' + forDriver + '.png'} alt={forDriver + ' icon'} className="size-4" />}
            />
        );
    }

    return (
        <IconStackGlow
            className={className}
            icon={
                <img
                    src={icon}
                    alt={props.alt || 'Database Icon'}
                    className="size-4"
                />
            }
        />
    );
}
