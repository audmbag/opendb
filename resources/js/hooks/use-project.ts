import type { FormComponentRef } from '@inertiajs/core';
import { useHttp, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { CloningDriverConfig } from '@/components/cloning/cloning.types';
import type { ProjectConfigFormProps } from '@/components/project/driver-config-form';
import type { TablesSelectorFormRef } from '@/components/project/tables-selector';
import type { ConnectionTestResult} from '@/hooks/use-driver-config';
import { useDriverConfigTestConnection } from '@/hooks/use-driver-config';
import { useTempProjectStore } from '@/store/use-project-store';
import type { DriverConfig } from '@/types/databases';
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

export type ConnectionTestState = ConnectionTestResult & {
    triggered: boolean;
};

const CONNECTION_TEST_DEFAULT: ConnectionTestState = {
    success: false,
    triggered: false,
    message: '',
    willCreateDatabase: false,
};


const useProjectDriverConfig = ({ onStart, onFinish }: ProjectInfoProps) => {

    const tempProject = useTempProjectStore((d) => d.tmpProject);
    const cloningDriverConfig = useTempProjectStore((state) => state.cloningDriverConfig,);


    const formRef = useRef<FormComponentRef<ProjectConfigFormProps>>(null);

    const { testConnection, processing: processingTest, errors: testErrors, resetAndClearErrors, } = useDriverConfigTestConnection();

    const [testConnectionState, setTestConnectionState] = useState(CONNECTION_TEST_DEFAULT);

    const { props: { project }, } = usePage<{ project: Project }>();

    const { post, optimistic, errors } = useHttp<ProjectConfigFormProps | null, { driverConfig: DriverConfig }>(null);

    const defaults = useMemo(() => {
        return { ...tempProject?.driver_config, ...cloningDriverConfig, ...project?.driver_config };
    }, [cloningDriverConfig, tempProject?.driver_config, project?.driver_config]);

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
            return Promise.resolve({
                driverConfig: tempProject?.driver_config,
            });
        }

        optimistic(() => formData);

        return await post(
            ProjectController.storeDriverConfig(tempProject?.id || project?.id).url,
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

    }, [onFinish, onStart, optimistic, project?.id, tempProject?.driver_config, tempProject?.id]);

    const handleTestConnection = useCallback(async () => {
        if (!formRef.current) {
            return;
        }

        setTestConnectionState(CONNECTION_TEST_DEFAULT);

        onStart?.();

        return await testConnection(
            formRef.current.getData() as DriverConfig & Pick<CloningDriverConfig, 'from_id'>,
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
    }, [onFinish, onStart, testConnection]);

    const resetData = useCallback(() => {
        formRef.current?.clearErrors();
        resetAndClearErrors();
        setTestConnectionState(CONNECTION_TEST_DEFAULT);
    }, [resetAndClearErrors]);


    return {
        errors: { ...testErrors, ...errors },
        formRef,
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
