import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import Modal from '@/components/modals/modal';
import {
    SmallSideBar,
    SmallSideBarContent,
    SmallSideBarProvider,
} from '@/components/sm-side-bar/small-side-bar';
import SmallSideBarBreadcrumb from '@/components/sm-side-bar/small-side-bar-breadcrumb';
import {
    useProjectSettingsDialog,
    useProjectSettingsNavItems,
} from '@/hooks/use-project-settings-modal';
import type { Project } from '@/types/models/project';

/**
 * The settings of the current project, opened from the main sidebar.
 */
export default function ProjectSettingsDialog() {
    const { t } = useLaravelReactI18n();
    const { isOpen, close, targetTab } = useProjectSettingsDialog();
    const projectSettingsNavItems = useProjectSettingsNavItems();
    const { project } = usePage<{ project: Project }>().props;

    return (
        <Modal
            title={t('projects.menu.settings')}
            description={t('projects.settings.description', { project: project.name })}
            open={isOpen}
            onOpenChange={(open) => !open && close()}
            className="h-150 sm:max-w-250"
        >
            <SmallSideBarProvider
                key={targetTab ?? 'general'}
                pages={projectSettingsNavItems}
                defaultPage={targetTab}
            >
                <ProjectSettingsContent projectName={project.name} />
            </SmallSideBarProvider>
        </Modal>
    );
}

const ProjectSettingsContent = ({ projectName }: { projectName: string }) => {
    return (
        <div className="h-full w-full overflow-hidden">
            <div className="flex h-full w-full flex-col overflow-hidden p-0 md:flex-row">
                <SmallSideBar className="pt-4" />
                <SmallSideBarContent
                    className="mt-2 md:mt-0"
                    header={
                        <header className="flex h-14 shrink-0 items-center gap-2 px-4">
                            <SmallSideBarBreadcrumb root={projectName} />
                        </header>
                    }
                />
            </div>
        </div>
    );
};
