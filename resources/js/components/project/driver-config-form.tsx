import { Form, usePage } from '@inertiajs/react';
import {
    ChevronDownIcon,
    ComputerIcon,
    DatabaseIcon,
    EthernetPortIcon,
    FlaskConicalIcon,
    KeyIcon,
    KeyRoundIcon,
    UsbIcon,
    UserIcon,
    UserKeyIcon,
} from 'lucide-react';
import React, { useImperativeHandle, useState } from 'react';
import { toast } from 'sonner';
import Collapsible from '@/components/collapsible';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import InputFile from '@/components/form/input-file';
import DatabaseDriverSelector from '@/components/project/database-driver-selector';
import SSHConfigForm from '@/components/project/ssh-config-form';
import type { ProjectPartFromRef} from '@/hooks/use-project';
import { useProjectDriverConfig } from '@/hooks/use-project';
import type { DriverConfig } from '@/types/databases';
import { DefaultDrivers } from '@/types/databases';
import type { Project } from '@/types/models/project';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';

export type ProjectConfigFormProps = Omit<DriverConfig, 'driver' | 'id'>;

export type DriverConfigFormProps = ProjectPartFromRef<void>;

type Props = {
    ref?: React.Ref<DriverConfigFormProps>;
    onStart?: () => void;
    onFinish?: () => void;
    onSuccess?: () => void;
};
const DriverConfigForm = ({ ref, onStart, onFinish, onSuccess }: Props) => {

    const {
        tempProject,
        formRef,
        setDriverConfig,
        testConnectionState,
        setTestConnectionState,
        handleTestConnection,
        submit,
        processingTest,
        testErrors,
        resetData,
    } = useProjectDriverConfig({ onStart, onFinish, onSuccess });

    const { props: { project } } = usePage<{ project: Project }>();

    const defaults = { ...tempProject?.driver_config, ...project?.driver_config };

    const [selectedDatabase, setSelectedDatabase] = useState(defaults?.driver_id || tempProject?.driver_config?.driver_id || DefaultDrivers.MYSQL,);

    useImperativeHandle(ref, () => ({ submit }));

    return (
        <Form
            ref={formRef}
            method="POST"
            action={ProjectController.storeDriverConfig(
                tempProject?.id || project?.id,
            )}
            onStart={onStart}
            onFinish={onFinish}
            onSuccess={(response) => {
                setDriverConfig(response.flash.driverConfig as DriverConfig);
                onSuccess?.();
            }}
            onError={(errors) => {
                if (errors.connectionFailed) {
                    setTestConnectionState({
                        state: false,
                        triggered: true,
                        message: errors.connectionFailed,
                    });
                    toast.error(errors.connectionFailed);
                }
            }}
            cancelOnUnmount
        >
            {({ errors: formErrors }) => {
                const errors = { ...testErrors, ...formErrors };

                return (
                    <div className="flex flex-col gap-4">
                        <DatabaseDriverSelector
                            selectedDriver={selectedDatabase}
                            onDriverChange={(e) => {
                                setSelectedDatabase(e.id);
                                resetData();
                            }}
                        />
                        {(tempProject?.driver_config?.id ||
                            defaults.driver_id) && (
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
                                        <Button
                                            icon={ChevronDownIcon}
                                            variant="outline"
                                        />
                                    </div>
                                </div>
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

                        <div>
                            <Button
                                variant="secondary"
                                icon={FlaskConicalIcon}
                                onClick={handleTestConnection}
                                processing={processingTest}
                                tooltipColor={
                                    testConnectionState.triggered
                                        ? testConnectionState.state
                                            ? 'success'
                                            : 'destructive'
                                        : 'default'
                                }
                                stateAppearance={
                                    testConnectionState.triggered
                                        ? testConnectionState.state
                                            ? 'success'
                                            : 'destructive'
                                        : undefined
                                }
                                tooltip={
                                    testConnectionState.triggered
                                        ? testConnectionState.message
                                        : undefined
                                }
                            >
                                Tester la connexion
                            </Button>
                        </div>
                    </div>
                );
            }}
        </Form>
    );
};

export default DriverConfigForm;
