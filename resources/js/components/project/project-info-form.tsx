import React, { useImperativeHandle } from 'react';
import InputFile from '@/components/form/input-file';
import type {
    ProjectInfo,
    ProjectPartFromRef,
} from '@/hooks/use-project';
import {
    useProjectInfo,
} from '@/hooks/use-project';
import type { Project } from '@/types/models/project';
import Input from '../form/input';
import Textarea from '../form/textarea';


export type ProjectInfoFormRef = ProjectPartFromRef<Promise<Partial<Project>>>;

type Props = {
    ref?: React.Ref<ProjectInfoFormRef>;
    defaults?: ProjectInfo;
    onStart?: () => void;
    onFinish?: () => void;
};


const ProjectInfoForm = ({
    ref,
    defaults,
    onStart,
    onFinish
}: Props) => {

    const { submit, data, errors, setData } = useProjectInfo({ defaults, onStart, onFinish });

    useImperativeHandle(ref, () => ({
        submit,
    }));

    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input
                label="Nom du projet"
                name="name"
                placeholder="Ex: Mon super projet"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                error={errors?.name}
            />
            <InputFile
                name={'path'}
                error={errors?.path}
                onlyFolders
                value={data.path}
                onChange={(e) => setData('path', e)}
            />
            <Textarea
                label="Description du projet"
                name="description"
                placeholder="Ex: Un projet pour gérer mes tâches"
                className="max-h-52 sm:col-span-2"
                error={errors?.description}
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
            />
        </div>
    );
};

export default ProjectInfoForm;
