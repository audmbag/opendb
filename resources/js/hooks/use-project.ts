import type { FormComponentRef } from '@inertiajs/core';
import { useHttp } from '@inertiajs/react';
import { useCallback, useImperativeHandle, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { ProjectConfigFormProps } from '@/components/project/driver-config-form';
import { useDriverConfigTestConnection } from '@/hooks/use-driver-config';
import { useTempProjectStore } from '@/store/use-project-store';
import type { DriverConfig } from '@/types/databases';
import type { Project } from '@/types/models/project';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';


export type ProjectInfo = Pick<Project, 'name' | 'path' | 'description' | 'id'>;

export type ProjectPartFromRef<T> = {
    submit: () => T;
};

export type ProjectInfoFormRef = ProjectPartFromRef<Promise<Partial<Project>>>;

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


const CONNECTION_TEST_DEFAULT = {
    state: false,
    triggered: false,
    message: '',
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

const useProjectDriverConfig = ({ onFinish, onStart, onSuccess }: ProjectInfoProps & { onSuccess?: () => void}) => {

    const tempProject = useTempProjectStore((d) => d.tmpProject);
    const setDriverConfig = useTempProjectStore((d) => d.setDriverConfig);

    const formRef = useRef<FormComponentRef<ProjectConfigFormProps>>(null);

    const { testConnection, processing: processingTest, errors: testErrors, resetAndClearErrors, } = useDriverConfigTestConnection();

    const [testConnectionState, setTestConnectionState] = useState(CONNECTION_TEST_DEFAULT);

    const submit = useCallback(() => {
        if (!formRef.current) {
            return;
        }

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
            ...formRef.current?.getData(),
            ssh: formRef.current?.getData().ssh || null,
        };

        if (tempProject?.driver_config?.id && !isDirtyObject(oldData, newData)) {
            onSuccess?.();

            return;
        }

        formRef.current.submit();
    }, [onSuccess, tempProject?.driver_config]);

    const handleTestConnection = useCallback(async () => {
        if (!formRef.current) {
            return;
        }

        setTestConnectionState(CONNECTION_TEST_DEFAULT);

        await testConnection(
            formRef.current.getData() as DriverConfig,
            ({ success, message }) => {
                setTestConnectionState({ state: success, triggered: true, message });
            },
        );
    }, [testConnection]);

    const resetData = useCallback(() => {
        formRef.current?.clearErrors();
        resetAndClearErrors();
        setTestConnectionState(CONNECTION_TEST_DEFAULT);
    }, [resetAndClearErrors]);


    return {
        tempProject,
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


export { useProjectInfo, useProjectDriverConfig };
