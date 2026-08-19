import {
    AstroidIcon,
    BrainIcon,
    PlugIcon,
    UnplugIcon,
} from 'lucide-react';
import React, {
    useCallback,
} from 'react';
import Button from '@/components/form/button';
import { Badge } from '@/components/reui/badge';
import { ProviderModelsModal } from '@/components/settings/provider-models-modal';
import type { Provider } from '@/types/models/provider';

type Props = {
    provider?: Provider;
    onConnect?: () => void;
    onDisconnect?: () => void;
    disconnectedProcessing?: boolean;
};




const ProviderItem = ({
    provider,
    onConnect,
    onDisconnect,
    disconnectedProcessing,
}: Props) => {

    const openModelManagerDialog = useCallback(async () => {
        if(!provider) {
            return
        }

        await ProviderModelsModal.call({ provider: provider });
    }, [provider]);


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
                    <span>{provider?.label || 'Fournisseur personnalisé'}</span>
                    {!provider && <Badge>Personnalisé</Badge>}
                </div>
                {provider && provider.is_connected ? (
                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="destructive"
                            icon={UnplugIcon}
                            onClick={onDisconnect}
                            confirmation={{
                                title: `Déconnecter le provider : ${provider.label}`,
                                description: `Vous n'aurais plus accès aux modèles du provider ${provider.label} dans OpenDB. Êtes-vous sûr de vouloir le déconnecter ?`,
                                icon: UnplugIcon,
                            }}
                            processing={disconnectedProcessing}
                        >
                            Déconnecter
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            icon={BrainIcon}
                            tooltip={'Gérer les modèles'}
                            onClick={openModelManagerDialog}
                        />
                    </div>
                ) : (
                    <Button
                        variant={provider ? 'secondary' : 'default'}
                        size="sm"
                        icon={PlugIcon}
                        onClick={onConnect}
                    >
                        Connecter
                    </Button>
                )}
            </div>
        </div>
    );
};



export default ProviderItem;
