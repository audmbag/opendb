import { Deferred, usePage } from '@inertiajs/react';
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
import { useProjectModal } from '@/hooks/use-project-modal';
import type { Project } from '@/types/models/project';
import CreateProjectModal from "./modals/create-project-modal";



export function RecentProjects() {

    const { props: { recentProjects, projectCount } } = usePage<{recentProjects: Project[], projectCount: number}>();

    const { setOpen } = useProjectModal()

    return (
        <>
            <CreateProjectModal />
            <Frame className="w-full">
                {!!projectCount && (
                    <FrameHeader className="flex flex-row items-center justify-between">
                        <FrameTitle>Projet récents</FrameTitle>
                        <div>
                            <Button
                                onClick={() => setOpen(true)}
                                icon={FolderPlusIcon}
                            >
                                Créer un projet
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
                                title="Aucun projet récent trouvé"
                                description="Vous n'avez pas encore ouvert de projet. Créez un nouveau projet pour commencer."
                                icon={FolderOpenIcon}
                                actions={[
                                    {
                                        label: 'Créer un projet',
                                        onClick: () => setOpen(true),
                                        icon: FolderPlusIcon,
                                    },
                                ]}
                            />
                        </FramePanel>
                    )}
                </Deferred>
            </Frame>
        </>
    );
}
