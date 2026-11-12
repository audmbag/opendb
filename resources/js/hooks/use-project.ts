import { useHttp, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { CloningDriverConfig } from '@/components/cloning/cloning.types';
import type { TablesSelectorFormRef } from '@/components/project/tables-selector';
import type { ConnectionTestResult} from '@/hooks/use-driver-config';
import { useDriverConfigTestConnection } from '@/hooks/use-driver-config';
import { trans } from '@/lib/i18n';
import { useTempProjectStore } from '@/store/use-project-store';
import type { DriverConfig, SSHConfiguration } from '@/types/databases';
import { DefaultDrivers } from '@/types/databases';
import type { Project } from '@/types/models/project';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';


export type ProjectInfo = Pick<Project, 'name' | 'path' | 'description' | 'id'>;

export type ProjectPartFromRef<T> = {
    submit: () => T;
};

export const isDirtyObject = function (
    oldValues: Record<string, any>,
    newValues: Record<string, any>,
): boolean {
    const oldKeys = Object.keys(oldValues);
    const newKeys = Object.keys(newValues);

    if (oldKeys.length !== newKeys.length) {
        return true;
    }

    return oldKeys.some((key) => oldValues[key] != newValues[key]);
};

type ProjectInfoProps = {
    defaults?: ProjectInfo;
    onStart?: () => void;
    onFinish?: () => void;
};


const useProjectInfo = ({ defaults, onFinish, onStart }: ProjectInfoProps) => {
    const tempProject = useTempProjectStore(d => d.tmpProject);
    const setTempProject = useTempProjectStore((state) => state.fill);

    const { errors, post, data, setData, optimistic } = useHttp<ProjectInfo, Project>({
        id: defaults?.id || tempProject?.id || '',
        name: defaults?.name || tempProject?.name || '',
        path: defaults?.path || tempProject?.path || '',
        description: defaults?.description || tempProject?.description || '',
    });

    const submit = useCallback(async () => {
            optimistic(() => ({ id: tempProject?.id }));

            const oldData: ProjectInfo = {
                id: tempProject?.id || '',
                name: tempProject?.name || '',
                path: tempProject?.path || '',
                description: tempProject?.description || '',
            };

            if (tempProject?.id && !isDirtyObject(oldData, data)) {
                return Promise.resolve(tempProject);
            }

            const response = await post(ProjectController.storeInfos.url(), {
                onStart: onStart,
                onFinish: onFinish,
            });

            setTempProject(response);

            return response;
        },
        [data, onFinish, onStart, optimistic, setTempProject, tempProject],
    );

    return {
        errors,
        post,
        data,
        setData,
        optimistic,
        submit,
    };
};

export type ConnectionTestState = ConnectionTestResult & {
    triggered: boolean;
};

const CONNECTION_TEST_DEFAULT: ConnectionTestState = {
    success: false,
    triggered: false,
    message: '',
    willCreateDatabase: false,
};


const DRIVER_CONNECTION_DEFAULTS: Partial<
    Record<DefaultDrivers, { host: string; port: number; username: string }>
> = {
    [DefaultDrivers.MYSQL]: { host: '127.0.0.1', port: 3306, username: 'root' },
    [DefaultDrivers.MARIADB]: { host: '127.0.0.1', port: 3306, username: 'root' },
    [DefaultDrivers.POSTGRESQL]: { host: '127.0.0.1', port: 5432, username: 'postgres' },
};

export type SSHConfigFormData = Record<keyof SSHConfiguration, string>;

/**
 * Valeurs saisies dans le formulaire de configuration du driver.
 */
export type DriverConfigFormData = {
    driver_id: string;
    host: string;
    port: string;
    username: string;
    password: string;
    database: string;
    ssh: SSHConfigFormData;
    create_database_if_not_exists: boolean;
};

/**
 * Données envoyées au serveur : seuls les champs affichés sont transmis.
 */
export type DriverConfigFormPayload = Pick<DriverConfigFormData, 'driver_id' | 'database'> &
    Partial<Pick<DriverConfigFormData, 'host' | 'port' | 'username' | 'password' | 'ssh'>> & {
        id?: string;
        from_id?: string;
        create_database_if_not_exists?: true;
    };

/**
 * Where an existing connection is saved, instead of the step of the project creation.
 */
export type DriverConfigSubmitTarget = {
    url: string;
    method: 'post' | 'put';
};

type ProjectDriverConfigProps = Pick<ProjectInfoProps, 'onStart' | 'onFinish'> & {
    sshEnabled?: boolean;
    cloning?: boolean;
    createDatabaseIfNotExists?: boolean;
    submitTo?: DriverConfigSubmitTarget;
};

const getConnectionDefaults = (
    driverId: string,
    defaults: Partial<DriverConfig>,
): Pick<DriverConfigFormData, 'host' | 'port' | 'username'> => {
    const driverDefaults = DRIVER_CONNECTION_DEFAULTS[driverId as DefaultDrivers];
    const saved = defaults.driver_id === driverId ? defaults : undefined;

    return {
        host: saved?.host || driverDefaults?.host || '',
        port: String(saved?.port || driverDefaults?.port || ''),
        username: saved?.username || driverDefaults?.username || '',
    };
};

const useProjectDriverConfig = ({
    onStart,
    onFinish,
    sshEnabled = true,
    cloning = false,
    createDatabaseIfNotExists = false,
    submitTo,
}: ProjectDriverConfigProps) => {

    const tempProject = useTempProjectStore((d) => d.tmpProject);
    const cloningDriverConfig = useTempProjectStore((state) => state.cloningDriverConfig,);

    const { testConnection, processing: processingTest, errors: testErrors, resetAndClearErrors, } = useDriverConfigTestConnection();

    const [testConnectionState, setTestConnectionState] = useState(CONNECTION_TEST_DEFAULT);

    const { props: { project }, } = usePage<{ project: Project }>();

    const { post, put, optimistic, errors } = useHttp<DriverConfigFormPayload | null, { driverConfig: DriverConfig }>(null);

    const defaults = useMemo(() => {
        return { ...tempProject?.driver_config, ...cloningDriverConfig, ...project?.driver_config };
    }, [cloningDriverConfig, tempProject?.driver_config, project?.driver_config]);

    const [data, setFormData] = useState<DriverConfigFormData>(() => {
        const driverId = defaults.driver_id || DefaultDrivers.MYSQL;

        return {
            driver_id: driverId,
            ...getConnectionDefaults(driverId, defaults),
            password: defaults.password || '',
            database: defaults.database || '',
            ssh: {
                host: defaults.ssh?.host || '',
                port: String(defaults.ssh?.port || 22),
                user: defaults.ssh?.user || '',
                id_file: defaults.ssh?.id_file || '',
            },
            create_database_if_not_exists: !!defaults.create_database_if_not_exists,
        };
    });

    const [sshOpen, setSshOpen] = useState(!!defaults.ssh);

    const setData = useCallback((values: Partial<DriverConfigFormData>) => {
        setFormData((prev) => ({ ...prev, ...values }));
    }, []);

    const setSshData = useCallback((values: Partial<SSHConfigFormData>) => {
        setFormData((prev) => ({ ...prev, ssh: { ...prev.ssh, ...values } }));
    }, []);

    const getData = useCallback((): DriverConfigFormPayload => {
        const isSqlite = data.driver_id === DefaultDrivers.SQLITE;
        const payload: DriverConfigFormPayload = {
            driver_id: data.driver_id,
            database: data.database,
        };

        if (defaults.id) {
            payload[cloning ? 'from_id' : 'id'] = String(defaults.id);
        }

        if (!isSqlite) {
            payload.host = data.host;
            payload.port = data.port;
            payload.username = data.username;
            payload.password = data.password;

            if (sshEnabled && sshOpen) {
                payload.ssh = data.ssh;
            }
        }

        if (createDatabaseIfNotExists && data.create_database_if_not_exists) {
            payload.create_database_if_not_exists = true;
        }

        return payload;
    }, [cloning, createDatabaseIfNotExists, data, defaults.id, sshEnabled, sshOpen]);

    const submit = useCallback(async () => {
        const formData = getData();

        const oldData = {
            database: tempProject?.driver_config?.database,
            driver_id: tempProject?.driver_config?.driver_id,
            host: tempProject?.driver_config?.host,
            id: tempProject?.driver_config?.id,
            password: tempProject?.driver_config?.password,
            port: tempProject?.driver_config?.port,
            username: tempProject?.driver_config?.username,
            ssh: tempProject?.driver_config?.ssh,
        };
        const newData = {
            ...formData,
            ssh: formData.ssh || null,
        };

        if (!submitTo && tempProject?.driver_config?.id && !isDirtyObject(oldData, newData)) {
            return Promise.resolve({
                driverConfig: tempProject?.driver_config,
            });
        }

        optimistic(() => formData);

        const send = submitTo?.method === 'put' ? put : post;

        return await send(
            submitTo?.url ?? ProjectController.storeDriverConfig(tempProject?.id || project?.id).url,
            {
                onStart: onStart,
                onFinish: onFinish,
                onError: errors => {
                    if (errors.connectionFailed) {
                        setTestConnectionState({
                            success: false,
                            triggered: true,
                            message: errors.connectionFailed,
                            willCreateDatabase: false,
                        });
                        toast.error(errors.connectionFailed);
                    }
                }
            },
        );

    }, [getData, onFinish, onStart, optimistic, post, project?.id, put, submitTo, tempProject?.driver_config, tempProject?.id]);

    const handleTestConnection = useCallback(async () => {
        setTestConnectionState(CONNECTION_TEST_DEFAULT);

        onStart?.();

        return await testConnection(
            getData() as unknown as DriverConfig & Pick<CloningDriverConfig, 'from_id'>,
            ({ success, message, willCreateDatabase }) => {
                setTestConnectionState({
                    success: success,
                    triggered: true,
                    message,
                    willCreateDatabase,
                });
                onFinish?.();
            },
            () => onFinish?.(),
        );
    }, [getData, onFinish, onStart, testConnection]);

    const resetData = useCallback(() => {
        resetAndClearErrors();
        setTestConnectionState(CONNECTION_TEST_DEFAULT);
    }, [resetAndClearErrors]);

    const changeDriver = useCallback((driverId: string) => {
        setFormData((prev) => {
            const sqliteToggled = (prev.driver_id === DefaultDrivers.SQLITE) !== (driverId === DefaultDrivers.SQLITE);

            return {
                ...prev,
                ...getConnectionDefaults(driverId, defaults),
                driver_id: driverId,
                database: sqliteToggled ? defaults.database || '' : prev.database,
            };
        });
        resetData();
    }, [defaults, resetData]);


    return {
        errors: { ...testErrors, ...errors },
        data,
        setData,
        setSshData,
        getData,
        changeDriver,
        sshOpen,
        setSshOpen,
        setTestConnectionState,
        testConnectionState,
        handleTestConnection,
        submit,
        processingTest,
        testErrors,
        resetData,
        defaults,
    };
};

type SaveProjectDataType = {
    agentDisabled: string[];
    cloningDisabled?: string[];
    cloningDriverConfig?: CloningDriverConfig | null;
};

const useSaveProject = () => {
    const tempProject = useTempProjectStore((d) => d.tmpProject);
    const clearTempProject = useTempProjectStore((d) => d.clear);

    const project = usePage<{ project: Project }>().props.project;

    const tablesSelectorRef = useRef<TablesSelectorFormRef>(null);

    const { post, processing, optimistic } = useHttp<SaveProjectDataType, Project>({ agentDisabled: [] });
    const cloningDriverConfig = useTempProjectStore((state) => state.cloningDriverConfig);

    const saveWithDisabledTables = useCallback(async () => {

        let targetUrl = ProjectController.saveWithDisabledTables.url({ project: tempProject?.id || project?.id })

        optimistic(() => {
            let data: SaveProjectDataType = {
                agentDisabled: tablesSelectorRef?.current?.submit()?.agentDisabled || [],
                ...(tablesSelectorRef?.current?.submit()?.cloningDisabled && {
                    cloningDisabled: tablesSelectorRef?.current?.submit()?.cloningDisabled,
                }),
            };

            if (cloningDriverConfig) {
                data = { ...data, cloningDriverConfig };

                targetUrl = ProjectController.saveWithCloning.url({ project: tempProject?.id || project?.id });
            }

            return data;
        });

        return await post(targetUrl, {
            onSuccess: () => clearTempProject(),
        });
    }, [clearTempProject, optimistic, project?.id, tempProject?.id]);

    return {
        processing,
        saveWithDisabledTables,
        tablesSelectorRef,
    };
};

const useOpenProjectFolder = (projectId?: Project['id']) => {
    const { post, processing } = useHttp<object, null>({});

    const openFolder = useCallback(() => {
        if (!projectId) {
            return;
        }

        post(ProjectController.openFolder.url(projectId), {
            onError: () => {
                toast.error(trans('projects.open_folder_failed'), {
                    id: 'open-project-folder-error',
                });
            },
            onHttpException: () => {
                toast.error(trans('projects.open_folder_failed'), {
                    id: 'open-project-folder-error',
                });

                return false;
            },
        }).catch(() => null);
    }, [post, projectId]);

    return { openFolder, processing };
};

export { useProjectInfo, useProjectDriverConfig, useSaveProject, useOpenProjectFolder };
