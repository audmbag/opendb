import { useLaravelReactI18n } from 'laravel-react-i18n';
import React from 'react';
import IconStackGlow from '@/components/icon-stack-glow';

type Props = {
    icon?: string;
    fill?: boolean;
    alt?: string;
    forDriver?: string;
} & React.ComponentProps<'img'>;

export default function DatabaseIconHelper({ icon, className, forDriver, ...props }: Props) {
    const { t } = useLaravelReactI18n();

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
                    alt={props.alt || t('projects.status.database')}
                    className="size-4"
                />
            }
        />
    );
}
