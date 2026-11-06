import {
    BotIcon,
    BrainIcon,
    FileDownIcon,
    InfoIcon,
    KeyboardIcon,
    MailIcon,
    Settings2Icon,
    UserIcon,
} from 'lucide-react';
import { Suspense, useCallback } from 'react';
import { lazy } from 'react';
import React, { createContext, useContext, useState } from 'react';
import { useHotkeys } from 'react-hotkeys-hook';
import type {
    SmallSidebarNavItem,
    SmallSidebarNavItemValue,
} from '@/components/sm-side-bar/types';

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
const MailSettings = lazy(() => import('../components/settings/mail-settings'));
const AboutSettings = lazy(
    () => import('../components/settings/about-settings'),
);

const lazyPage = (page: React.ReactNode) => (
    <Suspense fallback="Chargement...">{page}</Suspense>
);

export const SettingsNavItems: SmallSidebarNavItem[] = [
    {
        value: 'general',
        name: 'Général',
        icon: Settings2Icon,
        component: lazyPage(<GeneralSettings />),
        group: 'Bureau',
    },
    {
        value: 'shortcuts',
        name: 'Raccourcis',
        icon: KeyboardIcon,
        component: lazyPage(<ShortcutsSettings />),
        group: 'Bureau',
    },
    {
        value: 'account',
        name: 'Compte',
        icon: UserIcon,
        component: lazyPage(<AccountSettings />),
        group: 'Utilisateur',
    },
    {
        value: 'agent',
        name: 'Agent IA',
        icon: BotIcon,
        component: lazyPage(<AgentSettings />),
        group: 'Utilisateur',
    },
    {
        value: 'exports',
        name: 'Exports',
        icon: FileDownIcon,
        component: lazyPage(<ExportSettings />),
        group: 'Utilisateur',
    },
    {
        value: 'models',
        name: 'Modèles',
        icon: BrainIcon,
        component: lazyPage(<ModelsSettings />),
        group: 'Serveur',
    },
    {
        value: 'mail',
        name: 'Messagerie',
        icon: MailIcon,
        component: lazyPage(<MailSettings />),
        group: 'Serveur',
    },
    {
        value: 'about',
        name: 'À propos',
        icon: InfoIcon,
        component: lazyPage(<AboutSettings />),
        group: 'Application',
    },
];

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
