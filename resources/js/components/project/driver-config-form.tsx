import { Form } from '@inertiajs/react';
import {
    ComputerIcon,
    DatabaseIcon,
    EthernetPortIcon,
    UserIcon,
    UserKeyIcon,
} from 'lucide-react';
import React, { useImperativeHandle, useState } from 'react';
import Collapsible from '@/components/collapsible';
import Input from '@/components/form/input';
import InputFile from '@/components/form/input-file';
import DatabaseDriverSelector from '@/components/project/database-driver-selector';
import SSHConfigForm from '@/components/project/ssh-config-form';
import TestConnectionButton from '@/components/project/test-connection-button';
import type { TestResultProps } from '@/hooks/use-driver-config';
import type { ProjectPartFromRef} from '@/hooks/use-project';
import { useProjectDriverConfig } from '@/hooks/use-project';
import { useProjectModal } from '@/hooks/use-project-modal';
import type { DriverConfig } from '@/types/databases';
import { DefaultDrivers } from '@/types/databases';

export type ProjectConfigFormProps = Omit<DriverConfig, 'driver' | 'id'>;

export type DriverConfigFormProps = ProjectPartFromRef<void> & {
    initializeCloning: () => Promise<TestResultProps | undefined>;
};

type Props = {
    ref?: React.Ref<DriverConfigFormProps>;
    onStart?: () => void;
    onFinish?: () => void;
    onSuccess?: () => void;
    sshEnabled?: boolean;
};


const DriverConfigForm = ({ ref, onStart, onFinish, onSuccess, sshEnabled = true }: Props) => {

    const {
        tempProject,
        formRef,
        testConnectionState,
        handleTestConnection,
        submit,
        processingTest,
        resetData,
        project,
        errors,
    } = useProjectDriverConfig({ onStart, onFinish, onSuccess });

    const defaults = { ...tempProject?.driver_config, ...project?.driver_config };

    const [selectedDatabase, setSelectedDatabase] = useState(defaults?.driver_id || tempProject?.driver_config?.driver_id || DefaultDrivers.MYSQL);

    const { setOpen, setCloningOpen } = useProjectModal();

    const initializeCloning = async () => {
        // const result = await handleTestConnection();

        setOpen(false);

        // if (result && result.success) {
        setCloningOpen(true);

        //     return result;
        // }
    };

    useImperativeHandle(ref, () => ({
        submit,
        initializeCloning,
    }));

    return (
        <Form ref={formRef}>
            {() => (
                <div className="flex flex-col gap-4">
                    <DatabaseDriverSelector
                        selectedDriver={selectedDatabase}
                        onDriverChange={(e) => {
                            setSelectedDatabase(e.id);
                            resetData();
                        }}
                    />
                    {(tempProject?.driver_config?.id || defaults.driver_id) && (
                        <input
                            type="hidden"
                            name="id"
                            defaultValue={
                                tempProject?.driver_config?.id ||
                                defaults.driver_id
                            }
                            key={`config-${tempProject?.driver_config?.id || defaults.driver_id}`}
                        />
                    )}
                    {selectedDatabase !== DefaultDrivers.SQLITE && (
                        <>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="col-span-1 flex flex-col gap-4 sm:col-span-2 sm:flex-row">
                                    <Input
                                        label="Hôte"
                                        placeholder="Ex: localhost"
                                        className="flex-2"
                                        name="host"
                                        error={errors?.host}
                                        defaultValue={defaults?.host}
                                        icon={ComputerIcon}
                                    />
                                    <Input
                                        label="Port"
                                        placeholder={'Ex: 3306'}
                                        className="flex-1"
                                        name="port"
                                        type="number"
                                        defaultValue={defaults?.port}
                                        error={errors?.port}
                                        icon={EthernetPortIcon}
                                    />
                                </div>
                                <Input
                                    label="Nom d'utilisateur"
                                    placeholder="Ex: root"
                                    name="username"
                                    defaultValue={defaults?.username}
                                    error={errors?.username}
                                    icon={UserIcon}
                                />
                                <Input
                                    label="Mot de passe"
                                    placeholder="Ex: motdepasse"
                                    type="password"
                                    name="password"
                                    defaultValue={defaults?.password}
                                    error={errors?.password}
                                    icon={UserKeyIcon}
                                />
                                <div className="flex items-end gap-2 sm:col-span-2">
                                    <Input
                                        label="Nom de la base de données"
                                        placeholder="Ex: ma_base_de_donnees"
                                        className="flex-1"
                                        name="database"
                                        defaultValue={defaults?.database}
                                        error={errors?.database}
                                        icon={DatabaseIcon}
                                    />
                                </div>
                            </div>
                            {
                                sshEnabled &&
                                <Collapsible
                                    label={'Utiliser un tunnel SSH'}
                                    defaultOpen={!!defaults?.ssh}
                                    className="rounded-md data-[state=open]:bg-muted"
                                >
                                    <SSHConfigForm
                                        errors={errors}
                                        defaults={defaults?.ssh}
                                    />
                                </Collapsible>
                            }
                        </>
                    )}

                    {selectedDatabase === DefaultDrivers.SQLITE && (
                        <div>
                            <InputFile
                                name="database"
                                error={errors?.database}
                                targetFileExtensions={['sqlite', 'db']}
                                defaultValue={defaults?.database || ''}
                                icon={DatabaseIcon}
                            />
                        </div>
                    )}

                    <TestConnectionButton
                        state={testConnectionState}
                        processing={processingTest}
                        onTrigger={handleTestConnection}
                    />
                </div>
            )}
        </Form>
    );
};

export default DriverConfigForm;
