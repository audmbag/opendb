import { router, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { DatabaseIcon, SaveIcon } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import type { DriverConfigFormProps } from '@/components/project/driver-config-form';
import DriverConfigForm from '@/components/project/driver-config-form';
import type { DriverConfigSubmitTarget } from '@/hooks/use-project';
import type { Project } from '@/types/models/project';
import ProjectSettingsController from '@/wayfinder/actions/App/Http/Controllers/Project/ProjectSettingsController';

/**
 * The connection to the database of the project, saved only once the database answers.
 */
export default function DatabaseProjectSettings() {
    const { t } = useLaravelReactI18n();
    const { project } = usePage<{ project: Project }>().props;

    const formRef = useRef<DriverConfigFormProps>(null);
    const [processing, setProcessing] = useState(false);

    const submitTo = useMemo<DriverConfigSubmitTarget>(
        () => ({
            url: ProjectSettingsController.updateDatabase.url(project.id),
            method: 'put',
        }),
        [project.id],
    );

    const save = useCallback(async () => {
        try {
            const response = await formRef.current?.submit();

            if (response?.driverConfig) {
                toast.success(t('projects.settings.database.saved'));
                router.reload({ only: ['project'] });
            }
        } catch {
            // The errors of the connection are displayed by the form.
        }
    }, [t]);

    return (
        <div className="flex flex-col gap-4">
            <Alert
                variant="warning"
                title={t('projects.settings.database.change_title')}
                message={t('projects.settings.database.change_message')}
            />
            <CardFrame
                title={t('projects.settings.database.connection')}
                description={t('projects.settings.database.connection_description')}
                icon={DatabaseIcon}
                processing={processing}
            >
                <div className="flex flex-col gap-4">
                    <DriverConfigForm
                        ref={formRef}
                        submitTo={submitTo}
                        onStart={() => setProcessing(true)}
                        onFinish={() => setProcessing(false)}
                    />
                    <div className="flex justify-end">
                        <Button
                            icon={SaveIcon}
                            processing={processing}
                            onClick={save}
                        >
                            {t('projects.settings.database.save')}
                        </Button>
                    </div>
                </div>
            </CardFrame>
        </div>
    );
}
