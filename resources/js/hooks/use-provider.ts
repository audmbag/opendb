import { useHttp } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { useProviderStore } from '@/store/use-provider-store';
import type { Provider } from '@/types/models/provider';
import ProviderController from '@/wayfinder/actions/App/Http/Controllers/ProviderController';


type ProviderData = {
    connectedProviders: Provider[];
    disconnectedProviders: Provider[];
}

const useProvider = () => {

    const { get, processing } = useHttp<null, ProviderData>();
    const { delete: deleteRequest } = useHttp<null, { success: boolean, message: string }>(null);

    const [disconnectingId, setDisconnectingId] = useState<string | undefined>();

    const connectedProviders = useProviderStore.use.connectedProviders();
    const disconnectedProviders = useProviderStore.use.disconnectedProviders();
    const setProviders = useProviderStore.use.setProviders();

    const loadData = useCallback((reload: boolean = false) => {
        if (
            !reload &&
            (connectedProviders.length > 0 || disconnectedProviders.length > 0)
        ) {
            return;
        }

        get(ProviderController.index.url(), {
            onSuccess: (response) => {
                setProviders(response.connectedProviders, 'connected');
                setProviders(response.disconnectedProviders, 'disconnected');
            },
            onError: () => {
                toast.error('Impossible de charger les providers', {
                    description:
                        'Une erreur inattendue est survenue lors du chargement des providers.',
                    id: 'load-provider-error',
                });
            },
            onNetworkError: () => {
                toast.error('Impossible de charger les providers', {
                    description: 'Vérifiez votre connexion internet.',
                    id: 'load-provider-error',
                });
            },
        });
    }, []);

    const disconnect = useCallback((provider: Provider) => {
        setDisconnectingId(provider.id);
        deleteRequest(ProviderController.disconnect.url(provider.id), {
            onSuccess: ({ success, message }) => {

                loadData(true)

                toast[success ? 'success' : 'error'](
                    success ? 'Provider déconnecté' : 'Erreur lors de la déconnexion du provider',
                    {
                        description: message,
                    },
                );
                setDisconnectingId(undefined);
            },
        });
    }, [connectedProviders, disconnectedProviders, setProviders]);

    return {
        loadData,
        disconnect,
        disconnectingId,
        processing,
        connectedProviders,
        disconnectedProviders,
    };
};

export { useProvider };
