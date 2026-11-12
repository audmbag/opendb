import { router, useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Trash2Icon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import DeleteProjectDialog from '@/components/project/delete-project-dialog';
import { Muted } from '@/components/typograpfy/typography';
import type { Project } from '@/types/models/project';
import HomeController from '@/wayfinder/actions/App/Http/Controllers/HomeController';
import ProjectSettingsController from '@/wayfinder/actions/App/Http/Controllers/Project/ProjectSettingsController';

/**
 * The actions that cannot be undone, like deleting the project.
 */
export default function DangerZoneProjectSettings() {
    const { t } = useLaravelReactI18n();
    const { project } = usePage<{ project: Project }>().props;

    const [confirming, setConfirming] = useState(false);

    const http = useHttp<{ name: string }, { message: string }>({ name: '' });

    const deleteProject = (name: string) => {
        http.transform(() => ({ name }));
        http.delete(ProjectSettingsController.destroy.url(project.id), {
            onSuccess: (response) => {
                toast.success(response.message);
                setConfirming(false);
                router.visit(HomeController.url());
            },
        });
    };

    return (
        <CardFrame
            title={t('projects.settings.nav.danger_zone')}
            description={t('projects.settings.danger_zone.description')}
            icon={TriangleAlertIcon}
        >
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p className="text-sm font-medium">{t('projects.delete.title')}</p>
                    <Muted className="text-xs">
                        {t('projects.settings.danger_zone.delete_description')}
                    </Muted>
                </div>
                <Button
                    variant="destructive"
                    icon={Trash2Icon}
                    onClick={() => setConfirming(true)}
                >
                    {t('common.delete')}
                </Button>
            </div>
            <DeleteProjectDialog
                projectName={project.name}
                open={confirming}
                onOpenChange={(open) => {
                    setConfirming(open);
                    http.clearErrors();
                }}
                onConfirm={deleteProject}
                processing={http.processing}
                error={http.errors.name}
            />
        </CardFrame>
    );
}
