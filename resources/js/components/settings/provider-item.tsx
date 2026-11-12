import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AstroidIcon, BrainIcon, PlugIcon, UnplugIcon } from 'lucide-react';
import Button from '@/components/form/button';
import { Badge } from '@/components/reui/badge';
import type { Provider } from '@/types/models/provider';

type Props = {
    provider?: Provider;
    onConnect?: () => void;
    onDisconnect?: () => void;
    onManageModels?: () => void;
    disconnectedProcessing?: boolean;
};

const ProviderItem = ({
    provider,
    onConnect,
    onDisconnect,
    onManageModels,
    disconnectedProcessing,
}: Props) => {
    const { t } = useLaravelReactI18n();

    return (
        <div className="justify-between px-3 py-3">
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    {provider ? (
                        <img
                            src={provider.icon}
                            alt={provider.label}
                            className="h-5 w-5"
                        />
                    ) : (
                        <AstroidIcon className="size-5" />
                    )}
                    <span>{provider?.label || t('settings.models.custom_provider')}</span>
                    {!provider && <Badge>{t('settings.models.custom')}</Badge>}
                </div>
                {provider && provider.is_connected ? (
                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="destructive"
                            icon={UnplugIcon}
                            onClick={onDisconnect}
                            confirmation={{
                                title: t('settings.models.disconnect_provider', { provider: provider.label }),
                                description: t('settings.models.disconnect_provider_description', { provider: provider.label }),
                                icon: UnplugIcon,
                            }}
                            processing={disconnectedProcessing}
                        >
                            {t('settings.models.disconnect')}
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            icon={BrainIcon}
                            tooltip={t('settings.models.manage_models')}
                            onClick={onManageModels}
                        />
                    </div>
                ) : (
                    <Button
                        variant={provider ? 'secondary' : 'default'}
                        size="sm"
                        icon={PlugIcon}
                        onClick={onConnect}
                    >
                        {t('settings.models.connect')}
                    </Button>
                )}
            </div>
        </div>
    );
};

export default ProviderItem;
