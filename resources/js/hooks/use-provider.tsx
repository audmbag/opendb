import { useHttp } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { useProviderStore } from '@/store/use-provider-store';
import type { APIData } from '@/types/global';
import type { Provider } from '@/types/models/provider';
import ProviderController from '@/wayfinder/actions/App/Http/Controllers/ProviderController';


type ProviderData = APIData<Provider>


const useProvider = () => {

    const { get, processing } = useHttp<null, ProviderData>();
    const { delete: deleteRequest } = useHttp<null, { success: boolean, message: string }>();
    const { post: toggleModelRequest, processing: toggleModelProcessing } = useHttp<null, { status: boolean }>();

    const [disconnectingId, setDisconnectingId] = useState<string | undefined>();

    const providers = useProviderStore((state) => state.providers);
    const setProviders = useProviderStore((state) => state.setProviders);

    const loadData = useCallback((reload: boolean = false) => {
        if ( !reload && (providers.length > 0)) {
            return;
        }

        get(ProviderController.index.url(), {
            onSuccess: (response) => {
                setProviders(response.data);
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
    }, [providers.length, setProviders]);

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
    }, [loadData]);

    const connectedProviders = useMemo(() => providers.filter((provider) => provider.is_connected), [providers]);

    const disconnectedProviders = useMemo(() => providers.filter((provider) => !provider.is_connected), [providers]);

    const toggleProvider = useCallback((providerId: string, modelId: string, mutationCallback?: (status: boolean, error: boolean) => void) => {
        toggleModelRequest(
            ProviderController.toggleModel.url(providerId, { query: { modelId } }), {
                onSuccess: ({ status }) => {
                    setProviders(
                        providers.map((provider) => {
                            if (provider.id === providerId) {
                                return {
                                    ...provider,
                                    models: provider.models.map((model) => {
                                        if (model.id === modelId) {
                                            return { ...model, active: status };
                                        }

                                        return model;
                                    }),
                                };
                            }

                            return provider;
                        }),
                    );

                    mutationCallback?.(status, false)
                },
                onError: () => {
                    toast.error("Une erreur s'est produite");
                    mutationCallback?.(false, true)
                }
            },
        );
    }, [setProviders]);

    return {
        loadData,
        disconnect,
        disconnectingId,
        processing,
        connectedProviders,
        disconnectedProviders,
        toggleProvider,
        toggleModelProcessing,
    };
};

export { useProvider };
