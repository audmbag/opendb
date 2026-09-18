import {
    CheckCircle2Icon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CopyIcon,
} from 'lucide-react';
import React, {
    lazy,
    Suspense,
    useCallback,
    useMemo,
    useRef,
    useState,
} from 'react';
import Button from '@/components/form/button';
import Wizard from '@/components/form/wizard';
import type { DriverConfigFormProps } from '@/components/project/driver-config-form';
import type  { ProjectInfoFormRef } from '@/hooks/use-project';

const ProjectInfoForm = lazy(() => import('./project-info-form'));
const DriverConfigForm = lazy(() => import('./driver-config-form'));
const TablesSelector = lazy(() => import('./tables-selector'));


const ProjectForm = () => {

    const [currentStep, setCurrentStep] = useState(1);
    const [processing, setProcessing] = useState(false);

    const projectInfoFormRef = useRef<ProjectInfoFormRef>(null);
    const driverConfigRef = useRef<DriverConfigFormProps>(null);

    const handleNextStep = useCallback(async () => {
        if (projectInfoFormRef.current && currentStep === 1) {
            await projectInfoFormRef.current.submit();
            setCurrentStep(c => c + 1);
        }

        if(currentStep === 2 && driverConfigRef.current) {
            driverConfigRef.current.submit();
        }
    }, [currentStep]);


    const steps = useMemo(() => [
            {
                id: 'info',
                header: {
                    title: 'Project',
                    description: 'Informations du projet',
                },
                content: (
                    <Suspense fallback={'Chargement...'}>
                        <ProjectInfoForm
                            ref={projectInfoFormRef}
                            onStart={() => setProcessing(true)}
                            onFinish={() => setProcessing(false)}
                        />
                    </Suspense>
                ),
            },
            {
                id: 'database',
                header: {
                    title: 'Base de données',
                    description: 'Informations de la base de données',
                },
                content: (
                    <Suspense fallback={'Chargement...'}>
                        <DriverConfigForm
                            ref={driverConfigRef}
                            onStart={() => setProcessing(true)}
                            onFinish={() => setProcessing(false)}
                            onSuccess={() => setCurrentStep(c => c + 1)}
                        />
                    </Suspense>
                ),
            },
            {
                id: 'tables',
                header: {
                    title: 'Tables',
                    description: "Accès de l'agent IA",
                },
                content: (
                    <Suspense fallback={'Chargement...'}>
                        <TablesSelector />
                    </Suspense>
                ),
            },
        ],
        [],
    );

    return (
        <div className="flex flex-col gap-4 p-4">
            <Wizard
                steps={steps}
                value={currentStep}
                onValueChange={setCurrentStep}
            />

            <div className="flex items-center justify-between gap-2.5">
                <Button
                    variant="outline"
                    disabled={processing || currentStep === 1}
                    onClick={() => setCurrentStep((c) => c - 1)}
                    icon={ChevronLeftIcon}
                >
                    Précédent
                </Button>
                {currentStep < steps.length ? (
                    <Button
                        variant="outline"
                        onClick={handleNextStep}
                        processing={processing}
                        icon={ChevronRightIcon}
                        className={'flex-row-reverse'}
                    >
                        Suivant
                    </Button>
                ) : (
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            icon={CopyIcon}
                            tooltip="
                                Cloner et anonymiser la base de données plutôt que d'utiliser votre base de données originale
                            "
                        >
                            Cloner et anonymiser
                        </Button>
                        <Button
                            icon={CheckCircle2Icon}
                            className={'flex-row-reverse'}
                        >
                            Créer le projet
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
};


export default ProjectForm;
