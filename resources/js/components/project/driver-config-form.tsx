import { ComputerIcon, DatabaseIcon, EthernetPortIcon, UserIcon, UserKeyIcon } from 'lucide-react';
import React, { useCallback, useImperativeHandle } from 'react';
import type { CloningDriverConfig } from '@/components/cloning/cloning.types';
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
        data,
        setData,
        setSshData,
        getData,
        changeDriver,
        sshOpen,
        setSshOpen,
        testConnectionState,
        handleTestConnection,
        submit,
        processingTest,
        errors,
    } = useProjectDriverConfig({ onStart, onFinish, sshEnabled, cloning, createDatabaseIfNotExists });

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
        getCloningData: () => {
            const { driver_id, host, port, username, password, database, create_database_if_not_exists, from_id } = getData();

            return {
                driver_id,
                host,
                port,
                username,
                password,
                database,
                create_database_if_not_exists,
                from_id,
            } as unknown as CloningDriverConfig;
        },
    }));

    return (
        <div className="flex flex-col gap-4">
            <DatabaseDriverSelector
                selectedDriver={data.driver_id}
                onDriverChange={(e) => changeDriver(e.id)}
            />
            {data.driver_id !== DefaultDrivers.SQLITE && (
                <>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="col-span-1 flex flex-col gap-4 sm:col-span-2 sm:flex-row">
                            <Input
                                label="Hôte"
                                placeholder="Ex: localhost"
                                className="flex-2"
                                name="host"
                                error={errors?.host}
                                value={data.host}
                                onChange={(e) => setData({ host: e.target.value })}
                                icon={ComputerIcon}
                            />
                            <Input
                                label="Port"
                                placeholder={'Ex: 3306'}
                                className="flex-1"
                                name="port"
                                type="number"
                                value={data.port}
                                onChange={(e) => setData({ port: e.target.value })}
                                error={errors?.port}
                                icon={EthernetPortIcon}
                            />
                        </div>
                        <Input
                            label="Nom d'utilisateur"
                            placeholder="Ex: root"
                            name="username"
                            value={data.username}
                            onChange={(e) => setData({ username: e.target.value })}
                            error={errors?.username}
                            icon={UserIcon}
                        />
                        <Input
                            label="Mot de passe"
                            placeholder="Ex: motdepasse"
                            type="password"
                            name="password"
                            value={data.password}
                            onChange={(e) => setData({ password: e.target.value })}
                            error={errors?.password}
                            icon={UserKeyIcon}
                        />
                        <div className="flex items-end gap-2 sm:col-span-2">
                            <Input
                                label="Nom de la base de données"
                                placeholder="Ex: ma_base_de_donnees"
                                className="flex-1"
                                name="database"
                                value={data.database}
                                onChange={(e) => setData({ database: e.target.value })}
                                error={errors?.database}
                                icon={DatabaseIcon}
                            />
                        </div>
                    </div>
                    {sshEnabled && (
                        <Collapsible
                            label={'Utiliser un tunnel SSH'}
                            open={sshOpen}
                            onOpenChange={setSshOpen}
                            className="rounded-md pt-2.5 data-[state=open]:bg-muted"
                        >
                            <SSHConfigForm
                                errors={errors}
                                data={data.ssh}
                                onChange={setSshData}
                            />
                        </Collapsible>
                    )}
                </>
            )}

            {data.driver_id === DefaultDrivers.SQLITE && (
                <div>
                    <InputFile
                        name="database"
                        error={errors?.database}
                        targetFileExtensions={['sqlite', 'db']}
                        value={data.database}
                        onChange={(database) => setData({ database })}
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
                        checked={data.create_database_if_not_exists}
                        onCheckedChange={(checked) => setData({ create_database_if_not_exists: checked })}
                    />
                </div>
            )}

            <TestConnectionButton
                state={testConnectionState}
                processing={processingTest}
                onTrigger={handleTestConnection}
            />
        </div>
    );
};

export default DriverConfigForm;
