import { useHttp } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { EraserIcon, HardDriveIcon, InfoIcon, LoaderIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import SettingRow from '@/components/settings/setting-row';
import SettingsController from '@/wayfinder/actions/App/Http/Controllers/SettingsController';

type ApplicationInfo = {
    name: string;
    environment: string;
    phpVersion: string;
    laravelVersion: string;
    operatingSystem: string;
    dataDirectory: string;
    logDirectory: string;
};

const Path = ({ value }: { value: string }) => (
    <code className="rounded bg-muted px-1.5 py-0.5 text-xs select-all">
        {value}
    </code>
);

/**
 * The running application, where it keeps its data, and its cache.
 */
export default function AboutSettings() {
    const { t } = useLaravelReactI18n();
    const [info, setInfo] = useState<ApplicationInfo | null>(null);

    const loader = useHttp<null, { data: ApplicationInfo }>();
    const cache = useHttp<null, { message: string }>();

    useEffect(() => {
        loader.get(SettingsController.about.url(), {
            onSuccess: (response) => setInfo(response.data),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const clearCache = () => {
        cache.delete(SettingsController.clearCaches.url(), {
            onSuccess: (response) => toast.success(response.message),
        });
    };

    if (loader.processing || !info) {
        return (
            <div className="flex justify-center py-8">
                <LoaderIcon className="animate-spin" />
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <CardFrame
                title={info.name}
                description={t('settings.about.description')}
                icon={InfoIcon}
            >
                <SettingRow label={t('settings.about.system')}>{info.operatingSystem}</SettingRow>
                <SettingRow label={t('settings.about.environment')}>
                    {info.environment}
                </SettingRow>
                <SettingRow label="PHP">{info.phpVersion}</SettingRow>
                <SettingRow label="Laravel">{info.laravelVersion}</SettingRow>
            </CardFrame>
            <CardFrame
                title={t('settings.about.data')}
                description={t('settings.about.data_description', { name: info.name })}
                icon={HardDriveIcon}
            >
                <SettingRow
                    label={t('settings.about.data_directory')}
                    description={t('settings.about.data_directory_description')}
                >
                    <Path value={info.dataDirectory} />
                </SettingRow>
                <SettingRow
                    label={t('settings.about.logs')}
                    description={t('settings.about.logs_description')}
                >
                    <Path value={info.logDirectory} />
                </SettingRow>
                <SettingRow
                    label={t('settings.about.cache')}
                    description={t('settings.about.cache_description')}
                >
                    <Button
                        variant="outline"
                        size="sm"
                        icon={EraserIcon}
                        processing={cache.processing}
                        onClick={clearCache}
                    >
                        {t('settings.about.clear_cache')}
                    </Button>
                </SettingRow>
            </CardFrame>
        </div>
    );
}
