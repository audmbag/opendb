import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BrainCircuitIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CopyIcon,
    DatabaseIcon,
    HatGlassesIcon,
    SettingsIcon,
} from 'lucide-react';
import React, { useCallback, useMemo, useRef, useState } from 'react';
import CloningConfiguration from '@/components/cloning/cloning-configuration';
import type { CloningConfigurationFormRef } from '@/components/cloning/cloning-configuration';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type { CloningPayload } from '@/components/cloning/cloning.types';
import type { TableAnonymisationRef } from '@/components/cloning/tables-anonymisation';
import TablesAnonymisation from '@/components/cloning/tables-anonymisation';
import { DatabaseStatusBar } from '@/components/database-status-bar';
import Button from '@/components/form/button';
import type { WizardStep } from '@/components/form/wizard';
import Wizard from '@/components/form/wizard';
import CloningConfirmationModal from '@/components/modals/cloning-confirmation-modal';
import CloningProgressModal from '@/components/modals/cloning-progress-modal';
import DriverConfigForm from '@/components/project/driver-config-form';
import type {DriverConfigFormProps} from '@/components/project/driver-config-form';
import TablesSelector from '@/components/project/tables-selector';
import { isDirtyObject, useSaveProject } from '@/hooks/use-project';
import { useProjectModal } from '@/hooks/use-project-modal';
import { useTempProjectStore } from '@/store/use-project-store';

const CloningConnectionForm = () => {
    const { t } = useLaravelReactI18n();

    const { setCloningOpen, setOpen } = useProjectModal();
    const [currentStep, setCurrentStep] = useState(1);
    const tmpDriverConfig = useTempProjectStore((state) => state.tmpProject?.driver_config);

    const driverConfigRef = useRef<DriverConfigFormProps>(null);
    const tableAnonymisationRef = useRef<TableAnonymisationRef>(null);
    const configurationRef = useRef<CloningConfigurationFormRef>(null);
    const [processing, setProcessing] = useState(false);

    const [cloningPayload, setCloningPayload] = useState<CloningPayload | null>(null);
    const [confirmationOpen, setConfirmationOpen] = useState(false);
    const [cloningStarted, setCloningStarted] = useState(false);

    const buildDraft = useCloningStore((state) => state.buildDraft);

    const setCloningDriverConfig = useTempProjectStore((state) => state.setCloningDriverConfig);
    const cloningDriverConfig = useTempProjectStore((state) => state.cloningDriverConfig);

    const { tablesSelectorRef } = useSaveProject();

    const handleNextStep = useCallback(async () => {
        if (currentStep === 1 && driverConfigRef.current) {
            if(!isDirtyObject(driverConfigRef.current.getCloningData(), cloningDriverConfig || {})) {
                setCurrentStep((c) => c + 1);

                return;
            }

            const response = await driverConfigRef.current.testConnection();

            if(response && response.success) {
                setCloningDriverConfig(driverConfigRef.current.getCloningData());
                setCurrentStep((c) => c + 1);
            }
        }

        if(currentStep === 2 && tableAnonymisationRef.current) {
            tableAnonymisationRef.current.submit();
            setCurrentStep((c) => c + 1);
        }

        if(currentStep === 3) {
            setCurrentStep((c) => c + 1);
        }
    }, [cloningDriverConfig, currentStep, setCloningDriverConfig]);

    const openConfirmation = useCallback(() => {
        if (!configurationRef.current || !cloningDriverConfig) {
            return;
        }

        const tables = tmpDriverConfig?.tables_info ?? [];

        setCloningPayload({
            cloningDriverConfig,
            anonymisation: buildDraft(),
            options: configurationRef.current.submit(),
            agentDisabled: tables.filter((t) => !t.agentAccessEnabled).map((t) => t.name),
            cloningDisabled: tables.filter((t) => !t.cloningIncludingEnabled).map((t) => t.name),
        });
        setConfirmationOpen(true);
    }, [buildDraft, cloningDriverConfig, tmpDriverConfig?.tables_info]);

    const confirmCloning = useCallback(() => {
        setConfirmationOpen(false);
        setCloningStarted(true);
    }, []);

    const steps = useMemo<WizardStep[]>(() => [
            {
                id: 'database',
                header: {
                    title: t('cloning.ui.steps.target'),
                    description: t('cloning.ui.steps.target_description'),
                    icon: DatabaseIcon,
                },
                content: (
                    <DriverConfigForm
                        ref={driverConfigRef}
                        sshEnabled={false}
                        createDatabaseIfNotExists={true}
                        onStart={() => setProcessing(true)}
                        onFinish={() => setProcessing(false)}
                        cloning
                    />
                ),
            },
            {
                id: 'anonymisation',
                header: {
                    title: t('cloning.ui.steps.anonymisation'),
                    description: t('cloning.ui.steps.anonymisation_description'),
                    icon: HatGlassesIcon,
                },
                content: <TablesAnonymisation ref={tableAnonymisationRef} />,
            },
            {
                id: 'tables',
                header: {
                    title: t('projects.steps.tables'),
                    description: t('projects.steps.tables_description'),
                    icon: BrainCircuitIcon,
                },
                content: (
                    <TablesSelector
                        ref={tablesSelectorRef}
                        showHiddenSwitch={true}
                    />
                ),
            },
            {
                id: 'options',
                header: {
                    title: t('cloning.ui.steps.options'),
                    description: t('cloning.ui.configuration_options'),
                    icon: SettingsIcon,
                },
                content: (
                    <CloningConfiguration ref={configurationRef} />
                ),
            },
        ],
        [tablesSelectorRef, t],
    );

    return (
        <div className="flex flex-col">
            {tmpDriverConfig && (
                <DatabaseStatusBar
                    driver_config={tmpDriverConfig}
                    buttons={['reconnect', 'copy']}
                    showLabels={false}
                    className="ring-O rounded-none border-b border-border bg-accent shadow-none ring-transparent"
                />
            )}

            <div className="p-4">
                <Wizard
                    steps={steps}
                    value={currentStep}
                    onValueChange={setCurrentStep}
                />
            </div>

            <div className="flex items-center justify-between gap-2.5 border-t border-border bg-accent p-4">
                {currentStep === 1 ? (
                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => {
                            setCloningOpen(false);
                            setOpen(true, 2);
                        }}
                        tooltip={t('cloning.ui.cancel_tooltip')}
                    >
                        {t('cloning.ui.cancel')}
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => setCurrentStep((c) => c - 1)}
                        icon={ChevronLeftIcon}
                    >
                        {t('common.previous')}
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
                            {t('common.next')}
                        </Button>
                    </div>
                ) : (
                    <Button
                        icon={CopyIcon}
                        className="flex-row-reverse"
                        onClick={openConfirmation}
                    >
                        {t('cloning.ui.clone_and_create')}
                    </Button>
                )}
            </div>

            {tmpDriverConfig && cloningPayload && (
                <CloningConfirmationModal
                    open={confirmationOpen}
                    onOpenChange={setConfirmationOpen}
                    source={tmpDriverConfig}
                    payload={cloningPayload}
                    onConfirm={confirmCloning}
                />
            )}

            {cloningStarted && tmpDriverConfig && cloningPayload && (
                <CloningProgressModal
                    source={tmpDriverConfig}
                    payload={cloningPayload}
                    onCancel={() => setCloningStarted(false)}
                />
            )}
        </div>
    );
};


export default CloningConnectionForm;
