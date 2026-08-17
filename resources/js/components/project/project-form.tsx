import { Form } from '@inertiajs/react';
import { CheckIcon, FlaskConicalIcon, PlusIcon } from 'lucide-react';
import React, { lazy, Suspense, useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import { useDriverConfig } from '@/hooks/use-driver-config';
import type { Project } from '@/types/models/project';
import DriverConfigController from '@/wayfinder/actions/App/Http/Controllers/API/DriverConfigController';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';

const ProjectInfoForm = lazy(() => import('./project-info-form'));
const DriverConfigForm = lazy(() => import('./driver-config-form'));


type Props = {
    onClose: () => void;
    project?: Project;
    part?: 'config' | 'info';
    onSuccess?: () => void
};

const CONNECTION_TEST_DEFAULT = {
    state: false,
    triggered: false,
    message: '',
};

type FormRefHandle = Omit<React.ComponentRef<typeof Form>, 'getData'> & {
    getData: () => Project;
};


const ProjectForm = ({
    onClose,
    project,
    part,
    onSuccess
}: Props) => {
    const {
        testConnection,
        processing: processingTest,
        errors: testErrors,
        resetAndClearErrors,
    } = useDriverConfig();

    const [connectionTest, setConnectionTest] = useState(
        CONNECTION_TEST_DEFAULT,
    );

    const formRef = useRef<FormRefHandle>(null);

    const handleTestConnection = useCallback(() => {
        if (!formRef.current) {
            return;
        }

        setConnectionTest(CONNECTION_TEST_DEFAULT);

        testConnection(
            formRef.current.getData().driver_config,
            ({ success, message }) => {
                setConnectionTest({ state: success, triggered: true, message });
            },
        );
    }, [testConnection]);

    const resetData = useCallback(() => {
        formRef.current?.clearErrors();
        resetAndClearErrors();
        setConnectionTest(CONNECTION_TEST_DEFAULT);
    }, [resetAndClearErrors]);

    return (
        <Form<Project>
            ref={formRef}
            action={
                project
                    ? part === 'config'
                        ? DriverConfigController.update(project.driver_config.id)
                        : ProjectController.update(project.id as string)
                    : ProjectController.store()
            }
            method={project ? 'PATCH' : 'POST'}
            disableWhileProcessing
            onError={(errors) => {
                if (errors.connectionFailed) {
                    setConnectionTest({
                        state: false,
                        triggered: true,
                        message: errors.connectionFailed,
                    });
                    toast.error(errors.connectionFailed);
                }
            }}
            onSuccess={() => {
                toast.success(
                    ['config', 'info'].includes(part as string)
                        ? part === 'config'
                            ? 'La configuration du projet a été mise à jour avec succès.'
                            : 'Le projet a été mis à jour avec succès.'
                        : 'Le projet a été créé avec succès.',
                );
                onSuccess?.();
            }}
        >
            {({ processing, errors, submit }) => (
                <div className="flex h-full flex-col gap-4 p-4">
                    {(part === 'info' || !part) && (
                        <CardFrame
                            title="Informations du projet"
                            description="Remplissez les informations de base pour créer un nouveau projet"
                        >
                            <Suspense fallback={<div>Chargement...</div>}>
                                <ProjectInfoForm
                                    errors={errors}
                                    project={project}
                                />
                            </Suspense>
                        </CardFrame>
                    )}
                    {(part === 'config' || !part) && (
                        <CardFrame
                            title="Base de données"
                            description="Sélectionnez le type de base de données pour votre projet."
                        >
                            <Suspense fallback={<div>Chargement...</div>}>
                                <DriverConfigForm
                                    onDriverChanged={resetData}
                                    errors={{ ...testErrors, ...errors }}
                                    project={project}
                                />
                            </Suspense>
                        </CardFrame>
                    )}

                    <div className="flex justify-between">
                        <div className="flex items-center gap-2">
                            {(part === 'config' || !part) && (
                                <Button
                                    variant="ghost"
                                    icon={FlaskConicalIcon}
                                    onClick={handleTestConnection}
                                    disabled={processing}
                                    processing={processingTest}
                                    tooltipColor={
                                        connectionTest.triggered
                                            ? connectionTest.state
                                                ? 'success'
                                                : 'destructive'
                                            : 'default'
                                    }
                                    stateAppearance={
                                        connectionTest.triggered
                                            ? connectionTest.state
                                                ? 'success'
                                                : 'destructive'
                                            : undefined
                                    }
                                    tooltip={
                                        connectionTest.triggered
                                            ? connectionTest.message
                                            : undefined
                                    }
                                >
                                    Tester la connexion
                                </Button>
                            )}
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                onClick={onClose}
                                disabled={processing || processingTest}
                            >
                                Annuler
                            </Button>
                            <Button
                                icon={project ? CheckIcon : PlusIcon}
                                processing={processing}
                                disabled={processingTest}
                                onClick={() => {
                                    resetAndClearErrors();
                                    submit();
                                }}
                            >
                                {project ? 'Mettre à jour' : 'Confirmer'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </Form>
    );
};


export default ProjectForm;
