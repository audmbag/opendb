import React from 'react';
import Button from '@/components/form/button';
import Modal from '@/components/modals/modal'
import { SmallSideBar, SmallSideBarContent, SmallSideBarProvider, useSmallSideBar } from '@/components/sm-side-bar/small-side-bar';
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
} from '../ui/breadcrumb';


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

    const { currentPageData, setCurrentPage } = useSmallSideBar();

    return (
        <SmallSideBarProvider pages={SettingsNavItems} defaultPage={targetTab}>
            <div className="h-full w-full overflow-hidden">
                <div className="flex h-full w-full flex-col overflow-hidden p-0 md:flex-row">
                    <header className="flex h-13 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:hidden">
                        <div className="flex items-center gap-2 px-4">
                            <RenderBreadCrumbs
                                label={currentPageData?.name ?? ''}
                                onReset={() => setCurrentPage('general')}
                            />
                        </div>
                    </header>
                    <SmallSideBar className="pt-4" />
                    <SmallSideBarContent
                        header={
                            <header className="hidden h-14 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:flex">
                                <div className="flex items-center justify-between gap-2 px-4">
                                    <RenderBreadCrumbs
                                        label={currentPageData?.name ?? ''}
                                        onReset={() =>
                                            setCurrentPage('general')
                                        }
                                    />
                                </div>
                            </header>
                        }
                    />
                </div>
            </div>
        </SmallSideBarProvider>
    );
}
