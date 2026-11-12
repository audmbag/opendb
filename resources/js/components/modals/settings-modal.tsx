import { useLaravelReactI18n } from 'laravel-react-i18n';
import Modal from '@/components/modals/modal'
import { SmallSideBar, SmallSideBarContent, SmallSideBarProvider, useSmallSideBar } from '@/components/sm-side-bar/small-side-bar';
import SmallSideBarBreadcrumb, { SmallSideBarBreadcrumbLink } from '@/components/sm-side-bar/small-side-bar-breadcrumb';
import { useSettingsDialog, useSettingsNavItems } from '@/hooks/use-settings-modal';


export default function SettingsDialog() {
    const { t } = useLaravelReactI18n();
    const { isOpen, close, targetTab } = useSettingsDialog();
    const settingsNavItems = useSettingsNavItems();

    return (
        <Modal
            title={t('settings.title')}
            description={t('settings.description')}
            open={isOpen}
            onOpenChange={(open) => !open && close()}
            className={'h-150 sm:max-w-250'}
        >
            <SmallSideBarProvider
                pages={settingsNavItems}
                defaultPage={targetTab}
            >
                <SettingsContent />
            </SmallSideBarProvider>
        </Modal>
    );
}

/**
 * The settings, then the current page and the page opened inside it: the title goes back to the general settings.
 */
const RenderBreadCrumbs = ({ onReset }: { onReset: () => void }) => {
    const { t } = useLaravelReactI18n();

    return (
        <SmallSideBarBreadcrumb
            root={
                <SmallSideBarBreadcrumbLink onClick={onReset}>
                    {t('settings.title')}
                </SmallSideBarBreadcrumbLink>
            }
        />
    );
};


const SettingsContent = () => {

    const { setCurrentPage } = useSmallSideBar();

    return (
        <div className="h-full w-full overflow-hidden">
            <div className="flex h-full w-full flex-col overflow-hidden p-0 md:flex-row">
                <header className="flex h-13 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:hidden">
                    <div className="flex min-w-0 items-center gap-2 px-4">
                        <RenderBreadCrumbs onReset={() => setCurrentPage('general')} />
                    </div>
                </header>
                <SmallSideBar className="pt-4" />
                <SmallSideBarContent
                    className="mt-2 md:mt-0"
                    header={
                        <header className="hidden h-14 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:flex">
                            <div className="flex min-w-0 items-center justify-between gap-2 px-4">
                                <RenderBreadCrumbs onReset={() => setCurrentPage('general')} />
                            </div>
                        </header>
                    }
                />
            </div>
        </div>
    );
}
