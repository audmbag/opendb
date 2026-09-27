import type { FormComponentRef } from '@inertiajs/core';
import { useHttp, usePage } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import type { ProjectConfigFormProps } from '@/components/project/driver-config-form';
import { useDriverConfigTestConnection } from '@/hooks/use-driver-config';
import { useTempProjectStore } from '@/store/use-project-store';
import type { DriverConfig } from '@/types/databases';
import type { Project } from '@/types/models/project';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';
import type { TablesSelectorFormRef } from '@/components/project/tables-selector';
import { toast } from 'sonner';


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
    const setTempProject = useTempProjectStore.use.fill();

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




export type ConnectionTestState = {
    state: boolean;
    triggered: boolean;
    message?: string;
};

const CONNECTION_TEST_DEFAULT: ConnectionTestState = {
    state: false,
    triggered: false,
    message: '',
};


const useProjectDriverConfig = ({ onSuccess, onStart, onFinish }: ProjectInfoProps & { onSuccess?: () => void}) => {

    const tempProject = useTempProjectStore((d) => d.tmpProject);
    const setDriverConfig = useTempProjectStore((d) => d.setDriverConfig);

    const formRef = useRef<FormComponentRef<ProjectConfigFormProps>>(null);

    const { testConnection, processing: processingTest, errors: testErrors, resetAndClearErrors, } = useDriverConfigTestConnection();

    const [testConnectionState, setTestConnectionState] = useState(CONNECTION_TEST_DEFAULT);

    const { props: { project }, } = usePage<{ project: Project }>();

    const { post, optimistic, errors } = useHttp<ProjectConfigFormProps | null, { driverConfig: DriverConfig }>(null);

    const submit = useCallback(async () => {
        if (!formRef.current) {
            return;
        }

        const formData = formRef.current.getData();

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

        if (tempProject?.driver_config?.id && !isDirtyObject(oldData, newData)) {
            onSuccess?.();

            return;
        }

        optimistic(() => formData);

        await post(
            ProjectController.storeDriverConfig(tempProject?.id || project?.id).url,
            {
                onStart: onStart,
                onFinish: onFinish,
                onSuccess: ({ driverConfig }) => {
                    setDriverConfig(driverConfig);
                    onSuccess?.();
                },
                onError: errors => {
                    if (errors.connectionFailed) {
                        setTestConnectionState({
                            state: false,
                            triggered: true,
                            message: errors.connectionFailed,
                        });
                        toast.error(errors.connectionFailed);
                    }
                }
            },
        );

    }, [onFinish, onStart, onSuccess, optimistic, project?.id, setDriverConfig, tempProject?.driver_config?.database, tempProject?.driver_config?.driver_id, tempProject?.driver_config?.host, tempProject?.driver_config?.id, tempProject?.driver_config?.password, tempProject?.driver_config?.port, tempProject?.driver_config?.ssh, tempProject?.driver_config?.username, tempProject?.id]);

    const handleTestConnection = useCallback(async () => {
        if (!formRef.current) {
            return;
        }

        setTestConnectionState(CONNECTION_TEST_DEFAULT);

        onStart?.();

        const result = await testConnection(
            formRef.current.getData() as DriverConfig,
            ({ success, message }) => {
                setTestConnectionState({ state: success, triggered: true, message });
            },
        );

        onFinish?.();

        return result;
    }, [onFinish, onStart, testConnection]);

    const resetData = useCallback(() => {
        formRef.current?.clearErrors();
        resetAndClearErrors();
        setTestConnectionState(CONNECTION_TEST_DEFAULT);
    }, [resetAndClearErrors]);


    return {
        project,
        tempProject,
        errors: { ...testErrors, ...errors },
        formRef,
        setDriverConfig,
        setTestConnectionState,
        testConnectionState,
        handleTestConnection,
        submit,
        processingTest,
        testErrors,
        resetData,
    };
};


const useSaveProject = () => {
    const tempProject = useTempProjectStore((d) => d.tmpProject);
    const clearTempProject = useTempProjectStore((d) => d.clear);

    const project = usePage<{ project: Project }>().props.project;

    const tablesSelectorRef = useRef<TablesSelectorFormRef>(null);

    const { post, processing, optimistic } = useHttp<{tables: string[]}, Project>({
        tables: []
    });

    const saveWithDisabledTables = useCallback(async (onSuccess?: (project: Project) => void) => {
        if (!tablesSelectorRef.current) {
            return;
        }

        const disabledTables = tablesSelectorRef.current.submit();

        if(!disabledTables) {
            return;
        }

        optimistic(() => ({ tables: disabledTables }));

        await post(
            ProjectController.saveWithDisabledTables.url({
                project: tempProject?.id || project?.id,
            }),
            {
                onSuccess: response => {
                    clearTempProject();
                    onSuccess?.(response);
                },
            },
        );
    }, [clearTempProject, optimistic, project?.id, tempProject?.id]);

    return {
        processing,
        saveWithDisabledTables,
        tablesSelectorRef,
    };
};

export { useProjectInfo, useProjectDriverConfig, useSaveProject };
