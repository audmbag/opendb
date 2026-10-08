import { Form } from '@inertiajs/react';
import { ComputerIcon, DatabaseIcon, EthernetPortIcon, UserIcon, UserKeyIcon } from 'lucide-react';
import React, { useCallback, useImperativeHandle, useState } from 'react';
import  { CloningDriverConfig, CloningDriverConfigOptions } from '@/components/cloning/cloning.types';
import Collapsible from '@/components/collapsible';
import Input from '@/components/form/input';
import InputFile from '@/components/form/input-file';
import Switch from '@/components/form/switch';
import DatabaseDriverSelector from '@/components/project/database-driver-selector';
import SSHConfigForm from '@/components/project/ssh-config-form';
import TestConnectionButton from '@/components/project/test-connection-button';
import type { ConnectionTestResult } from '@/hooks/use-driver-config';
import type { ProjectPartFromRef} from '@/hooks/use-project';
import { useProjectDriverConfig } from '@/hooks/use-project';
import { useProjectModal } from '@/hooks/use-project-modal';
import { useTempProjectStore } from '@/store/use-project-store';
import type { DriverConfig } from '@/types/databases';
import { DefaultDrivers } from '@/types/databases';

const DRIVER_CONNECTION_DEFAULTS: Partial<
    Record<DefaultDrivers, { host: string; port: number; username: string }>
> = {
    [DefaultDrivers.MYSQL]: { host: '127.0.0.1', port: 3306, username: 'root' },
    [DefaultDrivers.MARIADB]: { host: '127.0.0.1', port: 3306, username: 'root' },
    [DefaultDrivers.POSTGRESQL]: { host: '127.0.0.1', port: 5432, username: 'postgres' },
};

export type ProjectConfigFormProps = Omit<DriverConfig, 'driver' | 'id'> & CloningDriverConfigOptions;

export type DriverConfigFormProps = ProjectPartFromRef<
    Promise<{ driverConfig: DriverConfig } | undefined>
> & {
    initializeCloning: () => Promise<
        { driverConfig: DriverConfig } | undefined
    >;
    testConnection: () => Promise<ConnectionTestResult | undefined>;
    getCloningData: () => CloningDriverConfig;
};

type Props = {
    ref?: React.Ref<DriverConfigFormProps>;
    onStart?: () => void;
    onFinish?: () => void;
    sshEnabled?: boolean;
    cloning?: boolean;
    createDatabaseIfNotExists?: boolean;
};



const DriverConfigForm = ({
    ref,
    onStart,
    onFinish,
    sshEnabled = true,
    cloning = false,
    createDatabaseIfNotExists = false,
}: Props) => {
    const {
        formRef,
        testConnectionState,
        handleTestConnection,
        submit,
        processingTest,
        resetData,
        errors,
        defaults,
    } = useProjectDriverConfig({ onStart, onFinish });

    const [selectedDatabase, setSelectedDatabase] = useState(defaults?.driver_id || DefaultDrivers.MYSQL);

    const driverDefaults = DRIVER_CONNECTION_DEFAULTS[selectedDatabase as DefaultDrivers];

    const isSavedDriver = defaults?.driver_id === selectedDatabase;

    const { setOpen, setCloningOpen } = useProjectModal();
    const setDriverConfig = useTempProjectStore((d) => d.setDriverConfig);

    const initializeCloning = useCallback(async () => {
        const result = await submit();

        if (result && result.driverConfig) {
            setOpen(false);
            setCloningOpen(true);

            setDriverConfig(result.driverConfig);

            return result;
        }
    }, [submit, setOpen, setCloningOpen, setDriverConfig]);

    useImperativeHandle(ref, () => ({
        submit,
        initializeCloning,
        testConnection: handleTestConnection,
        getCloningData: () => ({
            driver_id: selectedDatabase,
            host: formRef.current?.getData().host,
            port: formRef.current?.getData().port,
            username: formRef.current?.getData().username,
            password: formRef.current?.getData().password,
            database: formRef.current?.getData().database || '',
            create_database_if_not_exists: formRef.current?.getData().create_database_if_not_exists,
            from_id: formRef.current?.getData().from_id,
        }),
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
                    {defaults?.id && !cloning && (
                        <input
                            type="hidden"
                            name="id"
                            defaultValue={defaults?.id}
                            key={`config-${defaults?.id}`}
                        />
                    )}
                    {defaults?.id && cloning && (
                        <input
                            type="hidden"
                            name="from_id"
                            defaultValue={defaults?.id}
                            key={`from-config-${defaults?.id}`}
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
                                        key={`host-${selectedDatabase}`}
                                        error={errors?.host}
                                        defaultValue={
                                            (isSavedDriver && defaults?.host) ||
                                            driverDefaults?.host
                                        }
                                        icon={ComputerIcon}
                                    />
                                    <Input
                                        label="Port"
                                        placeholder={'Ex: 3306'}
                                        className="flex-1"
                                        name="port"
                                        key={`port-${selectedDatabase}`}
                                        type="number"
                                        defaultValue={
                                            (isSavedDriver && defaults?.port) ||
                                            driverDefaults?.port
                                        }
                                        error={errors?.port}
                                        icon={EthernetPortIcon}
                                    />
                                </div>
                                <Input
                                    label="Nom d'utilisateur"
                                    placeholder="Ex: root"
                                    name="username"
                                    key={`username-${selectedDatabase}`}
                                    defaultValue={
                                        (isSavedDriver && defaults?.username) ||
                                        driverDefaults?.username
                                    }
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
                            {sshEnabled && (
                                <Collapsible
                                    label={'Utiliser un tunnel SSH'}
                                    defaultOpen={!!defaults?.ssh}
                                    className="rounded-md pt-2.5 data-[state=open]:bg-muted"
                                >
                                    <SSHConfigForm
                                        errors={errors}
                                        defaults={defaults?.ssh}
                                    />
                                </Collapsible>
                            )}
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

                    {createDatabaseIfNotExists && (
                        <div>
                            <Switch
                                label="Créer la base de données si elle n'existe pas"
                                name="create_database_if_not_exists"
                                orientation="horizontal"
                                defaultChecked={defaults?.create_database_if_not_exists}
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
