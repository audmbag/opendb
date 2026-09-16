import { Deferred, Form, Link, usePage } from '@inertiajs/react';
import {
    FolderOpenIcon,
    FolderPlusIcon,
    PlugIcon,
    TrashIcon,
} from 'lucide-react';
import React, { memo, useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from "@/components/empty-state";
import Button from "@/components/form/button";
import { Frame, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import Tooltip from '@/components/tooltip';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { Project } from '@/types/models/project';
import ProjectController from "@/wayfinder/actions/App/Http/Controllers/ProjectController";
import DatabaseIconHelper from "./database-icon-helper";
import CreateProjectModal from "./project/create-project-modal";
import { H6, Muted, } from "./typograpfy/typography";
import { buttonVariants } from "./ui/button";
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';



const LoadingIndicator = memo(({ count = 3 }: { count?: number }) => (
    <>
        {[...Array(Math.min(count, 3)).keys()].map((key) => (
            <FramePanel
                key={key}
                className="flex flex-col flex-wrap justify-between gap-4 bg-card sm:flex-row"
            >
                <div>
                    <div className="flex flex-wrap items-center gap-2 pb-2">
                        <Skeleton className="h-8 w-8 rounded-lg" />
                        <Skeleton className="h-4 w-30" />
                        <Muted className="text-xs">•</Muted>
                        <Skeleton className="h-4 w-90" />
                    </div>
                    <Skeleton className="h-4 w-full" />
                    <div className="flex flex-wrap items-center gap-2 pt-2">
                        <Skeleton className="h-4 w-15" />
                        <Muted className="text-xs">•</Muted>
                        <Skeleton className="h-4 w-50" />
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Skeleton className="h-8 w-30" />
                </div>
            </FramePanel>
        ))}
    </>
));


type ProjectItemType = { project: Project } & React.ComponentProps<typeof FramePanel>

const ProjectItem = ({ project, className, ...props }: ProjectItemType) => {

    const formRef = useRef<React.ComponentRef<typeof Form>>(null);

    const showToast = useCallback(() => {
        toast.success(`Le projet ${project.name} a été supprimé avec succès.`, {
            duration: 5000,
        });
    }, [project.name]);

    const handleSubmit = useCallback(() => {
        formRef.current?.submit();
    }, []);

    return (
        <FramePanel
            className={cn(
                'flex flex-col justify-between gap-4 bg-card sm:flex-row',
                className,
            )}
            {...props}
        >
            <div>
                <div className="flex items-center gap-2 pb-2">
                    <div className="flex items-center gap-2">
                        <Tooltip value={project.driver_config.driver?.name}>
                            <DatabaseIconHelper
                                icon={project.driver_config.driver?.icon}
                                fill
                                className="size-8"
                            />
                        </Tooltip>
                        <H6>{project.name}</H6>
                    </div>
                    <Muted className="text-xs">•</Muted>
                    <Muted className="text-xs">{project.path}</Muted>
                </div>
                <Muted>{project.description}</Muted>
                <div className="flex items-center gap-2 pt-2">
                    <Muted className="text-xs">
                        {project.created_at_diff_for_humans}
                    </Muted>
                    <Muted className="text-xs">•</Muted>
                    <p className="text-xs">
                        {project.driver_config.connection_string}
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Link
                    href={ConversationController.home(project.id as string)}
                    className={buttonVariants({ variant: 'secondary' })}
                >
                    <PlugIcon />
                    Se connecter
                </Link>
                <Form
                    ref={formRef}
                    onSuccess={showToast}
                    method="DELETE"
                    action={ProjectController.delete(project.id as string)}
                >
                    {({ processing }) => {
                        return (
                            <Button
                                variant="destructive"
                                size="icon"
                                icon={TrashIcon}
                                tooltip={'Supprimer le projet'}
                                processing={processing}
                                onClick={handleSubmit}
                                confirmation={{
                                    title: 'Suppression',
                                    description: `Vous ête sur de vouloir supprimer le projet : ${project.name} ?`,
                                    confirmText: 'Supprimer',
                                    icon: TrashIcon,
                                }}
                            />
                        );
                    }}
                </Form>
            </div>
        </FramePanel>
    );
}


export function RecentProjects() {

    const { props: { recentProjects, projectCount } } = usePage<{recentProjects: Project[], projectCount: number}>();

    const [openCreateProjectForm, setOpenCreateProjectForm] = useState(false);

    return (
        <>
            <CreateProjectModal
                open={openCreateProjectForm}
                onOpenChange={setOpenCreateProjectForm}
            />
            <Frame className="w-full">
                {!!projectCount &&
                    <FrameHeader className="flex flex-row items-center justify-between">
                        <FrameTitle>Projet récents</FrameTitle>
                        <div>
                            <Button
                                onClick={() => setOpenCreateProjectForm(true)}
                                icon={FolderPlusIcon}
                            >
                                Créer un projet
                            </Button>
                        </div>
                    </FrameHeader>
                }
                <Deferred
                    fallback={<LoadingIndicator count={projectCount} />}
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
                                        onClick: () =>
                                            setOpenCreateProjectForm(true),
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
