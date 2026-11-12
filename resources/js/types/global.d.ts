import type { SharedPrivileges } from '@/types/models/privilege';
import type { User } from '@/types/models/user';
import '@inertiajs/core';

export interface APIData<T> {
    data: T[];
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: { user: User; privileges: SharedPrivileges | null };
            sidebarOpen?: boolean;
            appName: string;
        };
    }
}
