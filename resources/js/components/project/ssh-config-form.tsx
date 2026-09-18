import Input from '@/components/form/input';
import type { SSHConfiguration } from '@/types/databases';
import {
    ComputerIcon,
    EthernetPortIcon,
    FileKeyIcon,
    UserIcon,
} from 'lucide-react';


type Props = {
    errors?: Record<string, string>;
    defaults?: SSHConfiguration;
};

const SSHConfigForm = ({ defaults, errors }: Props) => {
    return (
        <div className="grid grid-cols-1 gap-4 rounded-lg bg-background p-4">
            <div className="col-span-1 flex flex-col gap-4 sm:flex-row">
                <Input
                    label="Hôte"
                    placeholder="Ex: ssh.example.com"
                    name="ssh.host"
                    error={errors?.['ssh.host']}
                    defaultValue={defaults?.host || ''}
                    className="flex-2"
                    icon={ComputerIcon}
                />
                <Input
                    label="Port"
                    placeholder="Ex: 22"
                    name="ssh.port"
                    error={errors?.['ssh.port']}
                    defaultValue={defaults?.port || 22}
                    type="number"
                    className="flex-1"
                    icon={EthernetPortIcon}
                />
            </div>
            <Input
                label="Utilisateur"
                placeholder="Ex: user"
                name="ssh.user"
                error={errors?.['ssh.user']}
                defaultValue={defaults?.user || ''}
                icon={UserIcon}
            />
            <Input
                label="Fichier d'identification"
                placeholder="Ex: /home/username/.ssh/id_rsa"
                name="ssh.id_file"
                error={errors?.['ssh.id_file']}
                defaultValue={defaults?.id_file || ''}
                description={
                    "Chemin vers le fichier d'identification SSH (clé privée). " +
                    ' La clé privée ne doit pas avoir de mot de passe.'
                }
                icon={FileKeyIcon}
            />
        </div>
    );
};

export default SSHConfigForm;
