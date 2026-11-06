import { usePage } from '@inertiajs/react';
import Modal from '@/components/modals/modal';
import {
    SmallSideBar,
    SmallSideBarContent,
    SmallSideBarProvider,
    useSmallSideBar,
} from '@/components/sm-side-bar/small-side-bar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import {
    ProjectSettingsNavItems,
    useProjectSettingsDialog,
} from '@/hooks/use-project-settings-modal';
import type { Project } from '@/types/models/project';

/**
 * The settings of the current project, opened from the main sidebar.
 */
export default function ProjectSettingsDialog() {
    const { isOpen, close, targetTab } = useProjectSettingsDialog();
    const { project } = usePage<{ project: Project }>().props;

    return (
        <Modal
            title="Paramètres du projet"
            description={`Gérer les paramètres du projet ${project.name}`}
            open={isOpen}
            onOpenChange={(open) => !open && close()}
            className="h-150 sm:max-w-250"
        >
            <SmallSideBarProvider
                key={targetTab ?? 'general'}
                pages={ProjectSettingsNavItems}
                defaultPage={targetTab}
            >
                <ProjectSettingsContent projectName={project.name} />
            </SmallSideBarProvider>
        </Modal>
    );
}

const ProjectSettingsBreadcrumbs = ({
    projectName,
    label,
}: {
    projectName: string;
    label: string;
}) => (
    <Breadcrumb>
        <BreadcrumbList>
            <BreadcrumbItem>{projectName}</BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
                <BreadcrumbPage>{label}</BreadcrumbPage>
            </BreadcrumbItem>
        </BreadcrumbList>
    </Breadcrumb>
);

const ProjectSettingsContent = ({ projectName }: { projectName: string }) => {
    const { currentPageData } = useSmallSideBar();

    return (
        <div className="h-full w-full overflow-hidden">
            <div className="flex h-full w-full flex-col overflow-hidden p-0 md:flex-row">
                <SmallSideBar className="pt-4" />
                <SmallSideBarContent
                    className="mt-2 md:mt-0"
                    header={
                        <header className="flex h-14 shrink-0 items-center gap-2 px-4">
                            <ProjectSettingsBreadcrumbs
                                projectName={projectName}
                                label={currentPageData?.name ?? ''}
                            />
                        </header>
                    }
                />
            </div>
        </div>
    );
};
