import {
    BrainIcon,
    KeyboardIcon,
    MailIcon,
    PaletteIcon,
    Settings2Icon,
} from 'lucide-react';
import  { Suspense, useCallback } from 'react';
import { lazy } from 'react';
import React, {
    createContext,
    useContext,
    useState,
} from 'react';
import { useHotkeys } from "react-hotkeys-hook";
import type {
    SmallSidebarNavItem,
    SmallSidebarNavItemValue,
} from '@/components/sm-side-bar/types';


const GeneralSettings = lazy(() => import('../components/settings/general-settings'))
const ThemeSettings = lazy(() => import('../components/settings/themes-settings'))
const ShortcutsSettings = lazy(() => import('../components/settings/shortcuts-settings'))
const ModelsSettings = lazy(() => import('../components/settings/models-settings'))
const MailSettings = lazy(() => import('../components/settings/mail-settings'))


export const SettingsNavItems: SmallSidebarNavItem[] = [
    {
        value: 'general',
        name: 'Général',
        icon: Settings2Icon,
        component: (
            <Suspense fallback="Chargement...">
                <GeneralSettings />
            </Suspense>
        ),
        group: 'Bureau',
    },
    {
        value: 'themes',
        name: 'Thèmes',
        icon: PaletteIcon,
        component: (
            <Suspense fallback="Chargement...">
                <ThemeSettings />
            </Suspense>
        ),
        group: 'Bureau',
    },
    {
        value: 'shortcuts',
        name: 'Raccourcis',
        icon: KeyboardIcon,
        component: (
            <Suspense fallback="Chargement...">
                <ShortcutsSettings />
            </Suspense>
        ),
        group: 'Bureau',
    },
    {
        value: 'models',
        name: 'Modèles',
        icon: BrainIcon,
        component: (
            <Suspense fallback="Chargement...">
                <ModelsSettings />
            </Suspense>
        ),
        group: 'Serveur',
    },
    {
        value: 'mail',
        name: 'Messagerie',
        icon: MailIcon,
        component: (
            <Suspense fallback="Chargement...">
                <MailSettings />
            </Suspense>
        ),
        group: 'Serveur',
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


const SettingsProvider = ({ children } : { children: React.ReactNode }) => {

    const [open_, setOpen_] = useState(false);
    const [targetTab, setTargetTab] = useState<SmallSidebarNavItemValue | undefined>(undefined);

    const toggle = useCallback((tab?: SmallSidebarNavItemValue) => {
        setOpen_(prev => !prev);
        setTargetTab(prev => tab || prev);
    }, [])

    useHotkeys("mod+,", () => {
        toggle(targetTab);
    }, { preventDefault: true, useKey: true, delimiter: "|" });


    const setTab = useCallback((tab: SmallSidebarNavItemValue) => {
        setTargetTab(tab);
    }, []);


    return (
        <SettingsContext value={{ isOpen: open_, open: toggle, targetTab, close: () => setOpen_(false), setTab }}>
            {children}
        </SettingsContext>
    );
}



const useSettingsDialog = () => {
    const context = useContext(SettingsContext);

    if (!context) {
        throw new Error("useSettingsDialog must be used within a SettingsProvider");
    }

    return context;
}




export { SettingsProvider, useSettingsDialog };
