import { useHttp } from '@inertiajs/react';
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
                description="L'application et son environnement."
                icon={InfoIcon}
            >
                <SettingRow label="Système">{info.operatingSystem}</SettingRow>
                <SettingRow label="Environnement">
                    {info.environment}
                </SettingRow>
                <SettingRow label="PHP">{info.phpVersion}</SettingRow>
                <SettingRow label="Laravel">{info.laravelVersion}</SettingRow>
            </CardFrame>
            <CardFrame
                title="Données"
                description={`Tout ce que ${info.name} enregistre sur cet ordinateur.`}
                icon={HardDriveIcon}
            >
                <SettingRow
                    label="Dossier des données"
                    description="Base de l'application, mémoires des agents, fichiers temporaires."
                >
                    <Path value={info.dataDirectory} />
                </SettingRow>
                <SettingRow
                    label="Journaux"
                    description="Les journaux de clonage et des erreurs."
                >
                    <Path value={info.logDirectory} />
                </SettingRow>
                <SettingRow
                    label="Cache"
                    description="Relit le schéma de vos bases et la liste des modèles des fournisseurs, par exemple après un changement fait hors de l'application. Rien n'est perdu."
                >
                    <Button
                        variant="outline"
                        size="sm"
                        icon={EraserIcon}
                        processing={cache.processing}
                        onClick={clearCache}
                    >
                        Vider le cache
                    </Button>
                </SettingRow>
            </CardFrame>
        </div>
    );
}
