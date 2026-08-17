import type {
    LucideIcon} from 'lucide-react';
import {
    BrainIcon,
    KeyboardIcon,
    PaletteIcon,
    Settings2Icon,
} from 'lucide-react';

import type { LazyExoticComponent} from 'react';
import  { Fragment} from 'react';
import React, { lazy, useMemo, useState, Suspense } from 'react';
import Button from '@/components/form/button';
import Modal from '@/components/modal'
import { Separator } from '@/components/ui/separator';
import { useSettingsDialog } from '@/hooks/use-settings-modal';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from './ui/breadcrumb';
import { SidebarMenuButton, SidebarProvider } from './ui/sidebar';

const navItems = [
    {
        label: 'Bureau',
        items: [
            {
                value: 'general',
                name: 'Général',
                icon: Settings2Icon,
                component: lazy(() => import('./settings/general-settings')),
            },
            {
                value: 'themes',
                name: 'Thèmes',
                icon: PaletteIcon,
                component: lazy(() => import('./settings/themes-settings')),
            },
            {
                value: 'shortcuts',
                name: 'Raccourcis',
                icon: KeyboardIcon,
                component: lazy(() => import('./settings/shortcuts-settings')),
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
                component: lazy(() => import('./settings/models-settings')),
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

type NaviItemValue = typeof navItems[number]['items'][number]['value'];

export default function SettingsDialog() {

    const { open, setOpen } = useSettingsDialog();

    return (
        <Modal
            title="Settings"
            description="Manage your settings"
            open={open}
            onOpenChange={setOpen}
            className={'h-150 sm:max-w-250'}
        >
            <SettingsContent />
        </Modal>
    );
}

const RenderBreadCrumbs = ({
    label,
    onReset,
}: {
    label: string;
    onReset: () => void;
}) => (
    <Breadcrumb>
        <BreadcrumbList>
            <BreadcrumbItem>
                <BreadcrumbLink asChild>
                    <Button variant="link" onClick={onReset} className="h-auto p-0">
                        Paramètres
                    </Button>
                </BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
                <BreadcrumbPage>{label}</BreadcrumbPage>
            </BreadcrumbItem>
        </BreadcrumbList>
    </Breadcrumb>
);


const SettingsContent = () => {

    const [currentSettingPage, setCurrentSettingPage] = useState<NaviItemValue>('models');

    const currentPage = useMemo(() => {
        for (const section of navItems) {
            const item = section.items.find((i) => i.value === currentSettingPage);

            if (item) {
                return item;
            }
        }

        return null;
    }, [currentSettingPage]);

    return (
        <SidebarProvider className="overflow-hidden min-h-[initial] h-full">
            <div className="h-full w-full overflow-hidden">
                <div className="flex h-full w-full flex-col overflow-hidden p-0 md:flex-row">
                    <header className="flex h-13 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:hidden">
                        <div className="flex items-center gap-2 px-4">
                            <RenderBreadCrumbs
                                label={currentPage?.name ?? ''}
                                onReset={() => setCurrentSettingPage('general')}
                            />
                        </div>
                    </header>
                    <div className="flex basis-auto flex-row gap-1 overflow-auto px-3 py-2 bg-sidebar md:basis-50 md:flex-col dark:[background:repeating-linear-gradient(45deg,var(--background),transparent_100px)]">
                        {navItems.map((section) => (
                            <Fragment key={section.label}>
                                <h3 className="hidden pt-4 text-xs font-medium text-muted-foreground md:block">
                                    {section.label}
                                </h3>
                                {section.items.map((item) => (
                                    <SidebarMenuButton
                                        key={item.value}
                                        className="w-auto shrink-0 flex-nowrap md:w-full"
                                        onClick={() =>
                                            setCurrentSettingPage(item.value)
                                        }
                                        isActive={
                                            currentSettingPage === item.value
                                        }
                                    >
                                        <item.icon />
                                        {item.name}
                                    </SidebarMenuButton>
                                ))}
                            </Fragment>
                        ))}
                    </div>
                    <Separator orientation="vertical" />
                    <div className="flex flex-1 flex-col overflow-y-hidden">
                        <header className="hidden h-14 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:flex">
                            <div className="flex items-center justify-between gap-2 px-4">
                                <RenderBreadCrumbs
                                    label={currentPage?.name ?? ''}
                                    onReset={() =>
                                        setCurrentSettingPage('general')
                                    }
                                />
                            </div>
                        </header>
                        <main className="mt-4 flex h-120 max-h-150 flex-1 flex-col overflow-auto px-4 pb-4 md:mt-0">
                            {currentPage && (
                                <Suspense fallback={<div>Chargement...</div>}>
                                    <currentPage.component />
                                </Suspense>
                            )}
                        </main>
                    </div>
                </div>
            </div>
        </SidebarProvider>
    );
}
