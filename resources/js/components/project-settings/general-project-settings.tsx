import { router, useHttp, usePage } from '@inertiajs/react';
import { FolderIcon, SaveIcon } from 'lucide-react';
import type React from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import InputFile from '@/components/form/input-file';
import Textarea from '@/components/form/textarea';
import { Muted } from '@/components/typograpfy/typography';
import type { Project } from '@/types/models/project';
import ProjectSettingsController from '@/wayfinder/actions/App/Http/Controllers/Project/ProjectSettingsController';

type ProjectInfoForm = Pick<Project, 'name' | 'description' | 'path'>;

/**
 * The name, the description and the working directory of the project.
 */
export default function GeneralProjectSettings() {
    const { project } = usePage<{ project: Project }>().props;

    const form = useHttp<ProjectInfoForm, { data: Project; message: string }>({
        name: project.name,
        description: project.description ?? '',
        path: project.path,
    });

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        form.put(ProjectSettingsController.updateInfo.url(project.id), {
            onSuccess: (response) => {
                toast.success(response.message);
                form.setDefaults();
                router.reload({ only: ['project'] });
            },
        });
    };

    return (
        <CardFrame
            title="Informations du projet"
            description="Le nom et la description affichés dans OpenDB, et le dossier de travail de l'agent."
            icon={FolderIcon}
        >
            <form className="flex flex-col gap-4" onSubmit={save}>
                <Input
                    label="Nom du projet"
                    name="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    error={form.errors.name}
                />
                <Textarea
                    label="Description du projet"
                    name="description"
                    className="max-h-52"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    error={form.errors.description}
                />
                <div className="flex flex-col gap-1">
                    <InputFile
                        name="path"
                        onlyFolders
                        value={form.data.path}
                        onChange={(path) => form.setData('path', path)}
                        error={form.errors.path}
                    />
                    <Muted className="text-xs">
                        L'agent y lit et y écrit ses fichiers (exports,
                        documents). Les fichiers de l'ancien dossier ne sont pas
                        déplacés.
                    </Muted>
                </div>
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        icon={SaveIcon}
                        processing={form.processing}
                        disabled={!form.isDirty}
                    >
                        Enregistrer
                    </Button>
                </div>
            </form>
        </CardFrame>
    );
}
