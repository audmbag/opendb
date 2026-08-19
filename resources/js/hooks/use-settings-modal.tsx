import type { LucideIcon} from 'lucide-react';
import {
    BrainIcon,
    KeyboardIcon,
    PaletteIcon,
    Settings2Icon,
} from 'lucide-react';
import  { LazyExoticComponent, useCallback } from 'react';
import { lazy } from 'react';
import React, {
    createContext,
    useContext,
    useState,
} from 'react';
import { useHotkeys } from "react-hotkeys-hook";


export const SettingsNavItems = [
    {
        label: 'Bureau',
        items: [
            {
                value: 'general',
                name: 'Général',
                icon: Settings2Icon,
                component: lazy(() => import('../components/settings/general-settings')),
            },
            {
                value: 'themes',
                name: 'Thèmes',
                icon: PaletteIcon,
                component: lazy(() => import('../components/settings/themes-settings')),
            },
            {
                value: 'shortcuts',
                name: 'Raccourcis',
                icon: KeyboardIcon,
                component: lazy(() => import('../components/settings/shortcuts-settings')),
            },
        ],
    },
    {
        label: 'Serveur',
        items: [
            {
                value: 'models',
                name: 'Modèles',
                icon: BrainIcon,
                component: lazy(() => import('../components/settings/models-settings')),
            },
        ],
    },
] as const satisfies {
    label: string;
    items: {
        value: string;
        name: string;
        icon: LucideIcon;
        component: LazyExoticComponent<() => React.ReactNode>;
    }[];
}[];

export type SettingNaviItemValue = (typeof SettingsNavItems)[number]['items'][number]['value'];


type SettingsContextType = {
    isOpen: boolean;
    open: (targetTab?: SettingNaviItemValue) => void;
    targetTab: SettingNaviItemValue | undefined;
    close: () => void;
    setTab: (tab: SettingNaviItemValue) => void;
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
    const [targetTab, setTargetTab] = useState<SettingNaviItemValue | undefined>(undefined);

    const toggle = useCallback((tab?: SettingNaviItemValue) => {
        setOpen_(prev => !prev);
        setTargetTab(prev => tab || prev);
    }, [])

    useHotkeys("mod+,", () => {
        toggle(targetTab);
    }, { preventDefault: true, useKey: true, delimiter: "|" });


    const setTab = useCallback((tab: SettingNaviItemValue) => {
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
