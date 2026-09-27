import type { LucideIcon } from 'lucide-react';
import type React from 'react';

export type SmallSidebarNavItem = {
    value: string;
    name: string;
    icon: LucideIcon;
    badge?: string;
    component?: React.ReactElement;
    group?: string;
};

export type SmallSidebarNavGroupItem = {
    label?: string;
    items: SmallSidebarNavItem[];
};

export type SmallSidebarNavItemValue = SmallSidebarNavItem['value'];
