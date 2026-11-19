import { Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { PlugIcon, TrashIcon } from 'lucide-react';
import React, { memo, useCallback, useState } from 'react';
import { toast } from 'sonner';
import { formatRelativeTime } from '@/components/ai/artifacts/record/format';
import DatabaseIconHelper from '@/components/database-icon-helper';
import Button from '@/components/form/button';
import DeleteProjectDialog from '@/components/project/delete-project-dialog';
import { FramePanel } from '@/components/reui/frame';
import Tooltip from '@/components/tooltip';
import { H6, Muted } from '@/components/typograpfy/typography';
import { buttonVariants } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { trans } from '@/lib/i18n';
import { projectEntryUrl } from '@/lib/last-project-page';
import { cn } from '@/lib/utils';
import type { Project } from '@/types/models/project';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';

type Props = { project: Project } & React.ComponentProps<typeof FramePanel>;

export const ProjectItemSkeleton = memo(({ count = 3 }: { count?: number }) => (
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

const ProjectItem = ({ project, className, ...props }: Props) => {
    const { t } = useLaravelReactI18n();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>(undefined);

    const deleteProject = useCallback(
        (name: string) => {
            router.delete(ProjectController.delete.url(project.id as string), {
                data: { name },
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) => setError(errors.name),
                onSuccess: () => {
                    setConfirming(false);
                    toast.success(
                        trans('projects.deleted', { project: project.name }),
                        {
                            duration: 5000,
                        },
                    );
                },
            });
        },
        [project.id, project.name],
    );

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
                        <Tooltip value={project.driver_config?.driver?.name}>
                            <DatabaseIconHelper
                                icon={project.driver_config?.driver?.icon}
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
                        {project.created_at ? formatRelativeTime(project.created_at) : project.created_at_diff_for_humans}
                    </Muted>
                    <Muted className="text-xs">•</Muted>
                    <p className="text-xs">
                        {project.driver_config?.connection_string}
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Link
                    href={projectEntryUrl(project.id as string)}
                    className={buttonVariants({ variant: 'secondary' })}
                >
                    <PlugIcon />
                    {t('projects.connect')}
                </Link>
                {project.can_manage && (
                    <Button
                        variant="destructive"
                        size="icon"
                        icon={TrashIcon}
                        tooltip={t('projects.delete.title')}
                        processing={processing}
                        onClick={() => setConfirming(true)}
                    />
                )}
            </div>
            <DeleteProjectDialog
                projectName={project.name}
                open={confirming}
                onOpenChange={(open) => {
                    setConfirming(open);
                    setError(undefined);
                }}
                onConfirm={deleteProject}
                processing={processing}
                error={error}
            />
        </FramePanel>
    );
};

export default ProjectItem;
