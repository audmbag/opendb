import { router, usePage } from '@inertiajs/react';
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
                toast.success(
                    'La connexion à la base de données a été enregistrée.',
                );
                router.reload({ only: ['project'] });
            }
        } catch {
            // The errors of the connection are displayed by the form.
        }
    }, []);

    return (
        <div className="flex flex-col gap-4">
            <Alert
                variant="warning"
                title="Changer de base de données"
                message="Les conversations, la mémoire de l'agent et les tables qui lui sont désactivées sont conservées : vérifiez l'accès aux tables si la nouvelle base est différente."
            />
            <CardFrame
                title="Connexion"
                description="La connexion est testée avant d'être enregistrée."
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
                            Enregistrer la connexion
                        </Button>
                    </div>
                </div>
            </CardFrame>
        </div>
    );
}
