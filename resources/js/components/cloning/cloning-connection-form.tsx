import { BrainCircuitIcon, CheckCircle2Icon, ChevronLeftIcon, ChevronRightIcon, CopyIcon, DatabaseIcon, HatGlassesIcon } from 'lucide-react';
import React, { useCallback, useMemo, useRef, useState } from 'react';
import { DatabaseStatusBar } from '@/components/database-status-bar';
import Button from '@/components/form/button';
import type { WizardStep } from '@/components/form/wizard';
import Wizard from '@/components/form/wizard';
import DriverConfigForm from '@/components/project/driver-config-form';
import type {DriverConfigFormProps} from '@/components/project/driver-config-form';
import TablesSelector from '@/components/project/tables-selector';
import { useSaveProject } from '@/hooks/use-project';
import { useProjectModal } from '@/hooks/use-project-modal';
import type { DriverConfig } from '@/types/databases';
import TablesAnonymisation from '@/components/cloning/tables-anonymisation';

const FAKE_DRIVER_CONFIG = {
    id: 28,
    driver_id: 'mysql',
    project_id: '01m2xcyf54hc00xpj2ht0rp1be',
    host: 'localhost',
    port: 3306,
    database: 'allingrandslacs',
    username: 'root',
    password: 'root',
    disabled_tables: ['users'],
    connection_string: 'mysql://root:[password]@127.0.0.1:3306/allingrandslacs',
    driver: {
        id: 'mysql',
        name: 'MySQL',
        default_host: 'localhost',
        default_port: 3306,
        default_username: 'root',
        icon: 'https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/mysql/mysql-original.svg',
    },
} as DriverConfig;



const CloningConnectionForm = () => {

    const { setCloningOpen, setOpen } = useProjectModal();
    const [currentStep, setCurrentStep] = useState(2);

    const driverConfigRef = useRef<DriverConfigFormProps>(null);
    const { tablesSelectorRef } = useSaveProject();
    const [processing, setProcessing] = useState(false);

    const handleNextStep = useCallback(async () => {
        if (currentStep === 1 && driverConfigRef.current) {
            driverConfigRef.current.submit();
        }
    }, [currentStep]);

    const steps = useMemo<WizardStep[]>(() => [
            {
                id: 'database',
                header: {
                    title: 'Base de données cible',
                    description: 'Informations de la base de données cible',
                    icon: DatabaseIcon,
                },
                content: <DriverConfigForm ref={driverConfigRef} sshEnabled={false} />,
            },
            {
                id: 'anonymisation',
                header: {
                    title: 'Anonymisation',
                    description: 'Anonymisation des colonnes de chaque table.',
                    icon: HatGlassesIcon,
                },
                content: <TablesAnonymisation />
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
        <div className="flex flex-col gap-4 p-4">
            <DatabaseStatusBar
                driver_config={FAKE_DRIVER_CONFIG}
                buttons={['reconnect', 'copy']}
                showLabels={false}
            />
            <Wizard
                steps={steps}
                value={currentStep}
                onValueChange={setCurrentStep}
            />
            <div className="flex items-center justify-between gap-2.5">
                {currentStep === 1 ? (
                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => {
                            setCloningOpen(false);
                            setOpen(true);
                        }}
                        tooltip="Annuler le clonage et garder la connexion existante"
                    >
                        Annuler le clonage
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => setCurrentStep((c) => c - 1)}
                        icon={ChevronLeftIcon}
                    >
                        Précédent
                    </Button>
                )}
                {currentStep < steps.length ? (
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={handleNextStep}
                            processing={processing}
                            disabled={processing}
                            icon={ChevronRightIcon}
                            className={'flex-row-reverse'}
                        >
                            Suivant
                        </Button>
                    </div>
                ) : (
                    <Button icon={CheckCircle2Icon}>Créer le projet</Button>
                )}
            </div>
        </div>
    );
};


export default CloningConnectionForm;
