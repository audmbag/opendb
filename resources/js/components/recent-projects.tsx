import { Deferred, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    FolderOpenIcon,
    FolderPlusIcon,
} from 'lucide-react';
import React from 'react';
import { EmptyState } from "@/components/empty-state";
import Button from "@/components/form/button";
import ProjectItem, {
    ProjectItemSkeleton,
} from '@/components/project/project-item';
import { Frame, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { usePrivileges } from '@/hooks/use-privileges';
import { useProjectModal } from '@/hooks/use-project-modal';
import type { Project } from '@/types/models/project';
import CreateProjectModal from "./modals/create-project-modal";



export function RecentProjects() {
    const { t } = useLaravelReactI18n();

    const { props: { recentProjects, projectCount } } = usePage<{recentProjects: Project[], projectCount: number}>();

    const { setOpen } = useProjectModal()
    const canCreate = usePrivileges().can('create_project');

    return (
        <>
            <CreateProjectModal />
            <Frame className="w-full">
                {!!projectCount && canCreate && (
                    <FrameHeader className="flex flex-row items-center justify-between">
                        <FrameTitle>{t('projects.recent')}</FrameTitle>
                        <div>
                            <Button
                                onClick={() => setOpen(true)}
                                icon={FolderPlusIcon}
                            >
                                {t('projects.create')}
                            </Button>
                        </div>
                    </FrameHeader>
                )}
                <Deferred
                    fallback={<ProjectItemSkeleton count={projectCount} />}
                    data="recentProjects"
                >
                    {recentProjects?.length ? (
                        recentProjects.map((project) => (
                            <ProjectItem key={project.id} project={project} />
                        ))
                    ) : (
                        <FramePanel>
                            <EmptyState
                                title={t('projects.no_recent')}
                                description={canCreate ? t('projects.no_recent_description') : t('projects.no_accessible_project_description')}
                                icon={FolderOpenIcon}
                                actions={canCreate ? [
                                    {
                                        label: t('projects.create'),
                                        onClick: () => setOpen(true),
                                        icon: FolderPlusIcon,
                                    },
                                ] : []}
                            />
                        </FramePanel>
                    )}
                </Deferred>
            </Frame>
        </>
    );
}
