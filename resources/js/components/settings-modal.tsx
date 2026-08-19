import  { Fragment} from 'react';
import React, { useMemo, useState, Suspense } from 'react';
import Button from '@/components/form/button';
import Modal from '@/components/modal'
import { Separator } from '@/components/ui/separator';
import type { SettingNaviItemValue} from '@/hooks/use-settings-modal';
import { SettingsNavItems,
    useSettingsDialog,
} from '@/hooks/use-settings-modal';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from './ui/breadcrumb';
import { SidebarMenuButton, SidebarProvider } from './ui/sidebar';


export default function SettingsDialog() {

    const { isOpen, close } = useSettingsDialog();

    return (
        <Modal
            title="Settings"
            description="Manage your settings"
            open={isOpen}
            onOpenChange={open => !open && close()}
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

    const { targetTab } = useSettingsDialog();

    const [currentSettingPage, setCurrentSettingPage] = useState<SettingNaviItemValue>(targetTab || 'general');

    const currentPage = useMemo(() => {
        for (const section of SettingsNavItems) {
            const item = section.items.find(
                (i) => i.value === currentSettingPage,
            );

            if (item) {
                return item;
            }
        }

        return null;
    }, [currentSettingPage]);

    return (
        <SidebarProvider className="h-full min-h-[initial] overflow-hidden">
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
                    <div className="flex basis-auto flex-row gap-1 overflow-auto bg-sidebar px-3 py-2 md:basis-50 md:flex-col dark:[background:repeating-linear-gradient(45deg,var(--background),transparent_100px)]">
                        {SettingsNavItems.map((section) => (
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
