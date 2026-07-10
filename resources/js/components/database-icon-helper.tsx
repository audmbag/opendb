import type { DatabaseType } from '@/types/databases';
import React, {  } from 'react';

type Props = {
    databaseType?: DatabaseType;
} & React.HTMLAttributes<HTMLSpanElement>;

export default function DatabaseIconHelper({ databaseType, ...props }: Props) {

    let icon = null;

    switch (databaseType) {
        case 'mysql':
            icon = '🐬';
            break;
        case 'postgresql':
            icon = '🐘';
            break;
        case 'sqlite':
            icon = '📦';
            break;
        case 'mariadb':
            icon = '🦭';
            break;
        default:
            icon = '❓';
    }

    return <span {...props}>{icon}</span>;
}