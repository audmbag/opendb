import { router, useHttp, usePage } from '@inertiajs/react';
import { FileDownIcon, SaveIcon } from 'lucide-react';
import type React from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import Select from '@/components/form/select';
import SettingRow from '@/components/settings/setting-row';
import type { UserSettings } from '@/types/models/user';
import SettingsController from '@/wayfinder/actions/App/Http/Controllers/SettingsController';

const formats = [
    { value: 'sql', label: 'SQL (script)' },
    { value: 'csv', label: 'CSV (un fichier par table)' },
];

const compressions = [
    { value: 'none', label: 'Aucune' },
    { value: 'zip', label: 'Zip (.zip)' },
    { value: 'gzip', label: 'Gzip (.gz)' },
    { value: 'tar', label: 'Tar (.tar)' },
];

/**
 * The format and the compression of the exports, proposed in the export dialog and used by the agent.
 */
export default function ExportSettings() {
    const { auth } = usePage().props;

    const form = useHttp<UserSettings['export'], { message: string }>({
        ...auth.user.settings.export,
    });

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        form.put(SettingsController.updateExport.url(), {
            onSuccess: (response) => {
                toast.success(response.message);
                form.setDefaults();
                router.reload({ only: ['auth'] });
            },
        });
    };

    return (
        <form onSubmit={save}>
            <CardFrame
                title="Exports"
                description="Les choix proposés par défaut à chaque export, par vous ou par l'agent."
                icon={FileDownIcon}
                actions={
                    <Button
                        type="submit"
                        size="sm"
                        icon={SaveIcon}
                        processing={form.processing}
                        disabled={!form.isDirty}
                    >
                        Enregistrer
                    </Button>
                }
            >
                <SettingRow
                    label="Format"
                    description="Un script SQL recrée les tables ; le CSV s'ouvre dans un tableur."
                >
                    <Select
                        name="format"
                        items={formats}
                        value={form.data.format}
                        onValueChange={(value) =>
                            form.setData(
                                'format',
                                value as UserSettings['export']['format'],
                            )
                        }
                        error={form.errors.format}
                    />
                </SettingRow>
                <SettingRow
                    label="Compression"
                    description="Sans compression, un export de plusieurs fichiers est tout de même regroupé dans un zip."
                >
                    <Select
                        name="compression"
                        items={compressions}
                        value={form.data.compression}
                        onValueChange={(value) =>
                            form.setData(
                                'compression',
                                value as UserSettings['export']['compression'],
                            )
                        }
                        error={form.errors.compression}
                    />
                </SettingRow>
            </CardFrame>
        </form>
    );
}
