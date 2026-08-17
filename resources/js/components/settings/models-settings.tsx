import {
    CpuIcon,
    LoaderIcon,
} from 'lucide-react';
import { Fragment, useCallback, useEffect } from 'react';
import ConnectProviderModal from '@/components/connect-provider-modal';
import { EmptyState } from '@/components/empty-state';
import ProviderItem from '@/components/settings/provider-item';
import { useProvider } from '@/hooks/use-provider';
import type { Provider } from '@/types/models/provider';
import CardFrame from '../card-frame';
import { Separator } from '../ui/separator';
import { ProviderModelsModal } from '@/components/settings/provider-models-modal';


const ProviderWrapper = ({
        processing,
        providers,
        onConnect,
        onDisconnect,
        connected = false,
        disconnectingId,
    }: {
        processing: boolean;
        providers: Provider[];
        onConnect: (provider?: Provider) => void;
        onDisconnect: (provider: Provider) => void;
        connected?: boolean;
        disconnectingId?: string;
    }) => {
        return (
            <CardFrame
                title={connected ? 'Fournisseurs connectés' : 'Fournisseurs'}
                description={
                    connected
                        ? undefined
                        : "Connecter un des fournisseurs par défaut ou ajouter un fournisseur personalisé compatible avec OpenAI via l'URL de base."
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
                                    disconnectedProcessing={
                                        disconnectingId === provider.id
                                    }
                                />
                                {index !== providers.length - 1 && (
                                    <Separator />
                                )}
                            </Fragment>
                        ))}
                        {providers.length === 0 && !processing && (
                            <EmptyState
                                icon={CpuIcon}
                                title="Aucun fournisseur connecté"
                                description="Connecter un fournisseur et activer ses modèles pour les utiliser dans OpenDB"
                            />
                        )}
                    </>
                )}
            </CardFrame>
        );
}

export default function ModelsSettings() {
    const {
        loadData,
        disconnectedProviders,
        connectedProviders,
        processing,
        disconnect,
        disconnectingId,
    } = useProvider();

    useEffect(() => {
        loadData();
    }, [loadData]);

    const handleConnect = useCallback(
        async (modelsProvider?: Provider) => {
            const result = await ConnectProviderModal.call({
                provider: modelsProvider,
            });

            if (result) {
                loadData(true);
            }
        },
        [loadData],
    );

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <ConnectProviderModal />
            <ProviderModelsModal />
            <ProviderWrapper
                processing={processing}
                providers={connectedProviders}
                onConnect={(provider) => handleConnect(provider)}
                onDisconnect={disconnect}
                disconnectingId={disconnectingId}
                connected
            />
            <ProviderWrapper
                processing={processing}
                disconnectingId={disconnectingId}
                providers={disconnectedProviders}
                onConnect={(provider) => handleConnect(provider)}
                onDisconnect={disconnect}
            />
        </div>
    );
}
