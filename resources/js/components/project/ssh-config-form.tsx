import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ComputerIcon,
    EthernetPortIcon,
    FileKeyIcon,
    UserIcon,
} from 'lucide-react';
import Input from '@/components/form/input';
import type { SSHConfigFormData } from '@/hooks/use-project';


type Props = {
    errors?: Record<string, string>;
    data: SSHConfigFormData;
    onChange: (values: Partial<SSHConfigFormData>) => void;
};

const SSHConfigForm = ({ data, onChange, errors }: Props) => {
    const { t } = useLaravelReactI18n();

    return (
        <div className="grid grid-cols-1 gap-4 rounded-lg bg-background p-4">
            <div className="col-span-1 flex flex-col gap-4 sm:flex-row">
                <Input
                    label={t('projects.form.host')}
                    placeholder={t('projects.form.ssh_host_placeholder')}
                    name="ssh.host"
                    error={errors?.['ssh.host']}
                    value={data.host}
                    onChange={(e) => onChange({ host: e.target.value })}
                    className="flex-2"
                    icon={ComputerIcon}
                />
                <Input
                    label={t('projects.form.port')}
                    placeholder={t('projects.form.ssh_port_placeholder')}
                    name="ssh.port"
                    error={errors?.['ssh.port']}
                    value={data.port}
                    onChange={(e) => onChange({ port: e.target.value })}
                    type="number"
                    className="flex-1"
                    icon={EthernetPortIcon}
                />
            </div>
            <Input
                label={t('projects.form.ssh_user')}
                placeholder={t('projects.form.ssh_user_placeholder')}
                name="ssh.user"
                error={errors?.['ssh.user']}
                value={data.user}
                    onChange={(e) => onChange({ user: e.target.value })}
                icon={UserIcon}
            />
            <Input
                label={t('projects.form.ssh_id_file')}
                placeholder={t('projects.form.ssh_id_file_placeholder')}
                name="ssh.id_file"
                error={errors?.['ssh.id_file']}
                value={data.id_file}
                    onChange={(e) => onChange({ id_file: e.target.value })}
                description={t('projects.form.ssh_id_file_description')}
                icon={FileKeyIcon}
            />
        </div>
    );
};

export default SSHConfigForm;
