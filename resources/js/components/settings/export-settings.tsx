import { router, useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { FileDownIcon, SaveIcon } from 'lucide-react';
import type React from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import Select from '@/components/form/select';
import SettingRow from '@/components/settings/setting-row';
import type { UserSettings } from '@/types/models/user';
import SettingsController from '@/wayfinder/actions/App/Http/Controllers/SettingsController';

const FORMATS = ['sql', 'csv'] as const;

const COMPRESSIONS = ['none', 'zip', 'gzip', 'tar'] as const;

/**
 * The format and the compression of the exports, proposed in the export dialog and used by the agent.
 */
export default function ExportSettings() {
    const { t } = useLaravelReactI18n();
    const { auth } = usePage().props;

    const formats = FORMATS.map((value) => ({
        value,
        label: t(`exports.formats.${value}`),
    }));
    const compressions = COMPRESSIONS.map((value) => ({
        value,
        label: t(`exports.compressions.${value}`),
    }));

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
                title={t('settings.export.title')}
                description={t('settings.export.description')}
                icon={FileDownIcon}
                actions={
                    <Button
                        type="submit"
                        size="sm"
                        icon={SaveIcon}
                        processing={form.processing}
                        disabled={!form.isDirty}
                    >
                        {t('common.save')}
                    </Button>
                }
            >
                <SettingRow
                    label={t('settings.export.format')}
                    description={t('settings.export.format_description')}
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
                    label={t('settings.export.compression')}
                    description={t('settings.export.compression_description')}
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
