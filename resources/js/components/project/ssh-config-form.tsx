import { useForm } from '@inertiajs/react';
import CardFrame from '@/components/card-frame';
import Input from '@/components/form/input';
import type { Project } from '@/types/models/project';


type Props = {
    project?: Project;
};

const SSHConfigForm = ({ project }: Props) => {

    const { errors } = useForm<Project | Project['driver_config']>();

    return (
        <div className="grid grid-cols-1 gap-4 p-4 rounded-lg bg-background">
            <div className="col-span-1 flex flex-col gap-4 sm:flex-row">
                <Input
                    label="Hôte"
                    placeholder="Ex: ssh.example.com"
                    name="driver_config.ssh.host"
                    error={errors['driver_config.ssh.host']}
                    defaultValue={project?.driver_config?.ssh?.host || ''}
                    className="flex-2"
                />
                <Input
                    label="Port"
                    placeholder="Ex: 22"
                    name="driver_config.ssh.port"
                    error={errors['driver_config.ssh.port']}
                    defaultValue={project?.driver_config?.ssh?.port || 22}
                    type="number"
                    className="flex-1"
                />
            </div>
            <Input
                label="Utilisateur"
                placeholder="Ex: user"
                name="driver_config.ssh.user"
                error={errors['driver_config.ssh.user']}
                defaultValue={project?.driver_config?.ssh?.user || ''}
            />
            <Input
                label="Fichier d'identification"
                placeholder="Ex: /home/username/.ssh/id_rsa"
                name="driver_config.ssh.id_file"
                error={errors['driver_config.ssh.id_file']}
                defaultValue={project?.driver_config?.ssh?.id_file || ''}
                description={
                    "Chemin vers le fichier d'identification SSH (clé privée). "
                    +" La clé privée ne doit pas avoir de mot de passe."
                }
            />
        </div>
    );
};

export default SSHConfigForm;
