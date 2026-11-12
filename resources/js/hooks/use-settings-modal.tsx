import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BotIcon,
    ChartNoAxesColumnIcon,
    BrainIcon,
    FileDownIcon,
    InfoIcon,
    KeyboardIcon,
    KeyRoundIcon,
    MailIcon,
    Settings2Icon,
    ShieldIcon,
    UserIcon,
    UsersIcon,
} from 'lucide-react';
import { Suspense, useCallback, useMemo } from 'react';
import { lazy } from 'react';
import React, { createContext, useContext, useState } from 'react';
import { useHotkeys } from 'react-hotkeys-hook';
import type {
    SmallSidebarNavItem,
    SmallSidebarNavItemValue,
} from '@/components/sm-side-bar/types';
import { usePrivileges } from '@/hooks/use-privileges';
import type { Privilege } from '@/types/models/privilege';

const GeneralSettings = lazy(
    () => import('../components/settings/general-settings'),
);
const ShortcutsSettings = lazy(
    () => import('../components/settings/shortcuts-settings'),
);
const AccountSettings = lazy(
    () => import('../components/settings/account-settings'),
);
const AgentSettings = lazy(
    () => import('../components/settings/agent-settings'),
);
const ExportSettings = lazy(
    () => import('../components/settings/export-settings'),
);
const ModelsSettings = lazy(
    () => import('../components/settings/models-settings'),
);
const UsageSettings = lazy(
    () => import('../components/settings/usage-settings'),
);
const MailSettings = lazy(() => import('../components/settings/mail-settings'));
const McpSettings = lazy(() => import('../components/settings/mcp-settings'));
const UsersSettings = lazy(
    () => import('../components/settings/users-settings'),
);
const RolesSettings = lazy(
    () => import('../components/settings/roles-settings'),
);
const AboutSettings = lazy(
    () => import('../components/settings/about-settings'),
);

const lazyPage = (page: React.ReactNode) => <Suspense>{page}</Suspense>;

/**
 * The sections of the settings, their name and group being keys of lang/{locale}/settings.php, with the privilege on
 * the whole instance a section needs to be shown.
 */
const SETTINGS_NAV_ITEMS: (SmallSidebarNavItem & { privilege?: Privilege })[] = [
    {
        value: 'general',
        name: 'settings.nav.general',
        icon: Settings2Icon,
        component: lazyPage(<GeneralSettings />),
        group: 'settings.nav.groups.desktop',
    },
    {
        value: 'shortcuts',
        name: 'settings.nav.shortcuts',
        icon: KeyboardIcon,
        component: lazyPage(<ShortcutsSettings />),
        group: 'settings.nav.groups.desktop',
    },
    {
        value: 'account',
        name: 'settings.nav.account',
        icon: UserIcon,
        component: lazyPage(<AccountSettings />),
        group: 'settings.nav.groups.user',
    },
    {
        value: 'agent',
        name: 'settings.nav.agent',
        icon: BotIcon,
        component: lazyPage(<AgentSettings />),
        group: 'settings.nav.groups.user',
    },
    {
        value: 'exports',
        name: 'settings.nav.exports',
        icon: FileDownIcon,
        component: lazyPage(<ExportSettings />),
        group: 'settings.nav.groups.user',
    },
    {
        value: 'mcp',
        name: 'settings.nav.mcp',
        icon: KeyRoundIcon,
        component: lazyPage(<McpSettings />),
        group: 'settings.nav.groups.user',
    },
    {
        value: 'models',
        name: 'settings.nav.models',
        icon: BrainIcon,
        component: lazyPage(<ModelsSettings />),
        group: 'settings.nav.groups.server',
        privilege: 'manage_providers',
    },
    {
        value: 'usage',
        name: 'settings.nav.usage',
        icon: ChartNoAxesColumnIcon,
        component: lazyPage(<UsageSettings />),
        group: 'settings.nav.groups.server',
    },
    {
        value: 'mail',
        name: 'settings.nav.mail',
        icon: MailIcon,
        component: lazyPage(<MailSettings />),
        group: 'settings.nav.groups.server',
    },
    {
        value: 'users',
        name: 'settings.nav.users',
        icon: UsersIcon,
        component: lazyPage(<UsersSettings />),
        group: 'settings.nav.groups.administration',
        privilege: 'create_user',
    },
    {
        value: 'roles',
        name: 'settings.nav.roles',
        icon: ShieldIcon,
        component: lazyPage(<RolesSettings />),
        group: 'settings.nav.groups.administration',
        privilege: 'create_user',
    },
    {
        value: 'about',
        name: 'settings.nav.about',
        icon: InfoIcon,
        component: lazyPage(<AboutSettings />),
        group: 'settings.nav.groups.application',
    },
];

/**
 * The sections of the settings the user may open, named in the language of the interface.
 */
export function useSettingsNavItems(): SmallSidebarNavItem[] {
    const { t } = useLaravelReactI18n();
    const { can } = usePrivileges();

    return useMemo(
        () =>
            SETTINGS_NAV_ITEMS.filter((item) => !item.privilege || can(item.privilege)).map((item) => ({
                value: item.value,
                icon: item.icon,
                component: item.component,
                name: t(item.name),
                group: item.group && t(item.group),
            })),
        [t, can],
    );
}

type SettingsContextType = {
    isOpen: boolean;
    open: (targetTab?: SmallSidebarNavItemValue) => void;
    targetTab: SmallSidebarNavItemValue | undefined;
    close: () => void;
    setTab: (tab: SmallSidebarNavItemValue) => void;
};

const SettingsContext = createContext<SettingsContextType>({
    isOpen: false,
    open: () => {},
    targetTab: undefined,
    close: () => {},
    setTab: () => {},
});

const SettingsProvider = ({ children }: { children: React.ReactNode }) => {
    const [open_, setOpen_] = useState(false);
    const [targetTab, setTargetTab] = useState<
        SmallSidebarNavItemValue | undefined
    >(undefined);

    const toggle = useCallback((tab?: SmallSidebarNavItemValue) => {
        setOpen_((prev) => !prev);
        setTargetTab((prev) => tab || prev);
    }, []);

    useHotkeys(
        'mod+,',
        () => {
            toggle(targetTab);
        },
        { preventDefault: true, useKey: true, delimiter: '|' },
    );

    const setTab = useCallback((tab: SmallSidebarNavItemValue) => {
        setTargetTab(tab);
    }, []);

    return (
        <SettingsContext
            value={{
                isOpen: open_,
                open: toggle,
                targetTab,
                close: () => setOpen_(false),
                setTab,
            }}
        >
            {children}
        </SettingsContext>
    );
};

const useSettingsDialog = () => {
    const context = useContext(SettingsContext);

    if (!context) {
        throw new Error(
            'useSettingsDialog must be used within a SettingsProvider',
        );
    }

    return context;
};

export { SettingsProvider, useSettingsDialog };
