import { router } from '@inertiajs/react';
import {
    BrainCircuitIcon,
    CheckCircle2Icon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CopyIcon,
    DatabaseIcon,
    FolderIcon,
} from 'lucide-react';
import React, {
    useCallback,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import Button from '@/components/form/button';
import type { WizardStep } from '@/components/form/wizard';
import Wizard from '@/components/form/wizard';
import type { DriverConfigFormProps } from '@/components/project/driver-config-form';
import DriverConfigForm from '@/components/project/driver-config-form';
import type { ProjectInfoFormRef } from '@/components/project/project-info-form';
import ProjectInfoForm from '@/components/project/project-info-form';
import TablesSelector from '@/components/project/tables-selector';
import { useSaveProject } from '@/hooks/use-project';
import { useProjectModal } from '@/hooks/use-project-modal';
import { useTempProjectStore } from '@/store/use-project-store';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';

const ProjectForm = () => {

    const { setOpen: setIsProjectModalOpen, step: currentStep, setStep: setCurrentStep } = useProjectModal();

    const [processing, setProcessing] = useState(false);

    const projectInfoFormRef = useRef<ProjectInfoFormRef>(null);
    const driverConfigRef = useRef<DriverConfigFormProps>(null);

    const { saveWithDisabledTables, tablesSelectorRef, processing: processingSave } = useSaveProject();
    const setDriverConfig = useTempProjectStore((d) => d.setDriverConfig);

    const [triggeredButton, setTriggeredButton] = useState<'nextStep' | 'cloning' | null>(null);

    const handleNextStep = useCallback(async () => {

        setTriggeredButton(() => 'nextStep');

        if (projectInfoFormRef.current && currentStep === 1) {
            const response = await projectInfoFormRef.current.submit();

            if (response) {
                setCurrentStep((c) => c + 1);
            }
        }

        if (currentStep === 2 && driverConfigRef.current) {
            const response = await driverConfigRef.current.submit();

            if(response && response.driverConfig) {
                setDriverConfig(response.driverConfig);
                setCurrentStep((c) => c + 1);
            }
        }
    }, [currentStep, setCurrentStep, setDriverConfig]);

    const saveProject = useCallback(async () => {
        setTriggeredButton(null);

        const project = await saveWithDisabledTables();

        if(project) {
            setIsProjectModalOpen(false);
            toast.success('Project créé avec succès');
            router.visit(ConversationController.home.url({ project: project.id }));
        }
    }, [saveWithDisabledTables, setIsProjectModalOpen]);


    const initializeCloning = useCallback(() => {
        setProcessing(true);
        setTriggeredButton('cloning');
        driverConfigRef.current?.initializeCloning();
    }, []);

    const steps = useMemo<WizardStep[]>(() => [
            {
                id: 'info',
                header: {
                    title: 'Project',
                    description: 'Informations du projet',
                    icon: FolderIcon,
                },
                content: (
                    <ProjectInfoForm
                        ref={projectInfoFormRef}
                        onStart={() => setProcessing(true)}
                        onFinish={() => setProcessing(false)}
                    />
                ),
            },
            {
                id: 'database',
                header: {
                    title: 'Base de données',
                    description: 'Informations de la base de données',
                    icon: DatabaseIcon,
                },
                content: (
                    <DriverConfigForm
                        ref={driverConfigRef}
                        onStart={() => setProcessing(true)}
                        onFinish={() => setProcessing(false)}
                    />
                ),
            },
            {
                id: 'tables',
                header: {
                    title: 'Tables',
                    description: "Accès de l'agent IA",
                    icon: BrainCircuitIcon,
                },
                content: <TablesSelector ref={tablesSelectorRef} />,
            },
        ],
        [tablesSelectorRef],
    );

    return (
        <div className="flex flex-col">
            <div className="p-4">
                <Wizard
                    steps={steps}
                    value={currentStep}
                    onValueChange={setCurrentStep}
                />
            </div>

            <div className="flex items-center justify-between gap-2.5 bg-accent p-4 border-t border-border">
                <Button
                    variant="outline"
                    disabled={processing || processingSave || currentStep === 1}
                    onClick={() => setCurrentStep((c) => c - 1)}
                    icon={ChevronLeftIcon}
                >
                    Précédent
                </Button>
                {currentStep < steps.length ? (
                    <div className="flex items-center gap-2">
                        {currentStep === 2 && (
                            <Button
                                variant="outline"
                                icon={CopyIcon}
                                processing={processing && triggeredButton === 'cloning'}
                                disabled={processing}
                                tooltip="Cloner et anonymiser la base de données plutôt que d'utiliser votre base de données originale"
                                onClick={initializeCloning}
                            >
                                Cloner et anonymiser
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={handleNextStep}
                            processing={processing && triggeredButton === 'nextStep'}
                            disabled={processing}
                            icon={ChevronRightIcon}
                            className={'flex-row-reverse'}
                        >
                            Suivant
                        </Button>
                    </div>
                ) : (
                    <Button
                        icon={CheckCircle2Icon}
                        onClick={saveProject}
                        processing={processingSave}
                    >
                        Créer le projet
                    </Button>
                )}
            </div>
        </div>
    );
};


export default ProjectForm;
