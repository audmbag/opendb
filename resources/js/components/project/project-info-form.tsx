import type { FormDataErrors } from "node_modules/@inertiajs/core/types/types"
import InputFile from '@/components/form/input-file';
import type { Project } from '@/types/models/project';
import Input from "../form/input";
import Textarea from "../form/textarea";

const ProjectInfoForm = ({
    errors,
    project,
}: {
    errors: FormDataErrors<Project>;
    project?: Project;
}) => {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input
                label="Nom du projet"
                name="name"
                placeholder="Ex: Mon super projet"
                error={errors.name}
                defaultValue={project?.name}
            />
            <InputFile
                name={'path'}
                error={errors.path}
                onlyFolders
                defaultValue={project?.path}
            />
            <Textarea
                label="Description du projet"
                name="description"
                placeholder="Ex: Un projet pour gérer mes tâches"
                className="max-h-52 sm:col-span-2"
                error={errors.description}
                defaultValue={project?.description}
            />
        </div>
    );
};



export default ProjectInfoForm;
