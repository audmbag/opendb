import { useLaravelReactI18n } from 'laravel-react-i18n';
import { BrainIcon, CpuIcon, LoaderIcon, PlugIcon } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import ConnectProviderForm from '@/components/settings/connect-provider-form';
import ProviderItem from '@/components/settings/provider-item';
import ProviderModels from '@/components/settings/provider-models';
import { useSmallSideBarSubPage } from '@/components/sm-side-bar/small-side-bar';
import { useProvider } from '@/hooks/use-provider';
import type { Provider } from '@/types/models/provider';
import CardFrame from '../card-frame';
import { Separator } from '../ui/separator';

const ProviderWrapper = ({
    processing,
    providers,
    onConnect,
    onDisconnect,
    onManageModels,
    connected = false,
    disconnectingId,
}: {
    processing: boolean;
    providers: Provider[];
    onConnect: (provider?: Provider) => void;
    onDisconnect: (provider: Provider) => void;
    onManageModels: (provider: Provider) => void;
    connected?: boolean;
    disconnectingId?: string;
}) => {
    const { t } = useLaravelReactI18n();

    return (
        <CardFrame
            title={connected ? t('settings.models.connected_providers') : t('settings.models.providers')}
            description={
                connected
                    ? undefined
                    : t('settings.models.providers_description')
            }
            innerPadding={false}
        >
            {processing ? (
                <div className="flex justify-center py-4">
                    <LoaderIcon className="animate-spin" />
                </div>
            ) : (
                <>
                    {!connected && (
                        <>
                            <ProviderItem onConnect={() => onConnect()} />
                            <Separator />
                        </>
                    )}
                    {providers.map((provider, index) => (
                        <Fragment key={provider.id}>
                            <ProviderItem
                                provider={provider}
                                onConnect={() => onConnect(provider)}
                                onDisconnect={() => onDisconnect(provider)}
                                onManageModels={() => onManageModels(provider)}
                                disconnectedProcessing={
                                    disconnectingId === provider.id
                                }
                            />
                            {index !== providers.length - 1 && <Separator />}
                        </Fragment>
                    ))}
                    {providers.length === 0 && !processing && (
                        <EmptyState
                            icon={CpuIcon}
                            title={t('settings.models.no_providers')}
                            description={t('settings.models.no_providers_description')}
                        />
                    )}
                </>
            )}
        </CardFrame>
    );
};

/**
 * A page opened inside the models settings: the connection of a provider, or the models of a connected one.
 */
type ModelsSubPage =
    | { type: 'connect'; provider?: Provider }
    | { type: 'models'; providerId: string };

export default function ModelsSettings() {
    const { t } = useLaravelReactI18n();
    const {
        loadData,
        disconnectedProviders,
        connectedProviders,
        processing,
        disconnect,
        disconnectingId,
    } = useProvider();
    const [subPage, setSubPage] = useState<ModelsSubPage | null>(null);

    // The provider is read from the store, which the switches of its models keep up to date.
    const managedProvider =
        subPage?.type === 'models'
            ? connectedProviders.find(
                  (provider) => provider.id === subPage.providerId,
              )
            : undefined;
    const connectedProvider =
        subPage?.type === 'connect' ? subPage.provider : undefined;

    useSmallSideBarSubPage(
        subPage === null
            ? null
            : {
                  label:
                      (managedProvider ?? connectedProvider)?.label ??
                      t('settings.models.custom_provider'),
                  onBack: () => setSubPage(null),
              },
    );

    useEffect(() => {
        loadData();
    }, [loadData]);

    if (subPage?.type === 'connect') {
        return (
            <div className="flex flex-col gap-4 pt-0.5">
                <CardFrame
                    title={t('settings.providers.connect_title', {
                        provider:
                            connectedProvider?.label ??
                            t('settings.providers.a_custom_provider'),
                    })}
                    icon={PlugIcon}
                >
                    <ConnectProviderForm
                        provider={connectedProvider}
                        onCancel={() => setSubPage(null)}
                        onSuccess={() => {
                            loadData(true);
                            setSubPage(null);
                        }}
                    />
                </CardFrame>
            </div>
        );
    }

    if (managedProvider) {
        return (
            <div className="flex min-h-0 flex-1 flex-col pt-0.5">
                <CardFrame
                    className="min-h-0 flex-1"
                    title={t('settings.models.manage_provider_models', {
                        provider: managedProvider.label,
                    })}
                    icon={BrainIcon}
                    innerPadding={false}
                    innerClassName="flex min-h-0 flex-col"
                >
                    <ProviderModels provider={managedProvider} />
                </CardFrame>
            </div>
        );
    }

    const connect = (provider?: Provider) =>
        setSubPage({ type: 'connect', provider });
    const manageModels = (provider: Provider) =>
        setSubPage({ type: 'models', providerId: provider.id });

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <ProviderWrapper
                processing={processing}
                providers={connectedProviders}
                onConnect={connect}
                onDisconnect={disconnect}
                onManageModels={manageModels}
                disconnectingId={disconnectingId}
                connected
            />
            <ProviderWrapper
                processing={processing}
                disconnectingId={disconnectingId}
                providers={disconnectedProviders}
                onConnect={connect}
                onDisconnect={disconnect}
                onManageModels={manageModels}
            />
        </div>
    );
}
