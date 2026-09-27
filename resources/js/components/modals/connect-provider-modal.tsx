import { Form } from '@inertiajs/react';
import { PlugIcon } from 'lucide-react';
import type { FormDataErrors } from 'node_modules/@inertiajs/core/types/types';
import { Fragment } from 'react';
import { createCallable } from 'react-call';
import { toast } from 'sonner';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import KeyValue from '@/components/form/key-value';
import Modal from '@/components/modals/modal';
import ModelModalHeader from '@/components/settings/model-modal-header';
import type { Provider } from '@/types/models/provider';
import ProviderController from '@/wayfinder/actions/App/Http/Controllers/ProviderController';

type Props = {
    provider?: Provider;
};

type ProviderConnectResult = {
    provider: Provider;
} | null

const ConnectProviderModal = createCallable<Props, ProviderConnectResult>(
    ({ provider, call }) => {
        return (
            <Modal
                open={!call.ended}
                onOpenChange={() => call.end(null)}
                className="h-auto sm:max-w-170"
                scrollable
            >
                <ConnectProviderModalContent
                    provider={provider}
                    onCancel={() => call.end(null)}
                    onSuccess={call.end}
                />
            </Modal>
        );
    },
    1000,
);


const ConstomProviderFiledls = (errors: FormDataErrors<Record<string, any>>) => {
    return (
        <Fragment>
            <Input
                label="ID du provider"
                name="id"
                placeholder="mon-fournisseur"
                description="Lettres minuscules, chiffres, traits d'union ou tirets bas"
                error={errors.id}
            />
            <Input
                label="Nom d'affichage"
                name="label"
                placeholder="Ex: Mon Fournisseur"
                error={errors.label}
            />
            <Input
                label="Url de base"
                name="api_url"
                placeholder="Ex: https://api.monfournisseur.com/v1"
                error={errors.api_url}
            />
            <Input
                label="Clé d'API"
                name="api_key"
                placeholder="Ex: sk-abc123def456..."
                error={errors.api_key}
            />
            <KeyValue
                label="Modèles"
                description="Ajouter des modèles que vous souhaitez utiliser avec ce provider."
                name={"models"}
                keyPlaceholder="ID du modèle (ex: gpt-4)"
                valuePlaceholder="Nom d'affichage (ex: GPT-4)"
                addLabel="Ajouter un modèle"
                minItems={1}
                error={errors.models}
            />
        </Fragment>
    );
};

const ConnectProviderModalContent = ({
    provider,
    onCancel,
    onSuccess,
}: {
    provider?: Provider;
    onCancel: () => void;
    onSuccess: (result: NonNullable<ProviderConnectResult>) => void;
}) => {
    return (
        <Form
            className="flex flex-col gap-4 p-4"
            onSuccess={(page) => {
                if(page.flash.error) {
                    toast.error(page.flash.error as string, {
                        duration: 10000
                    });

                    return
                }

                toast.success(page.flash.success as string);

                if (provider) {
                    onSuccess({ provider });
                }
            }}
            action={
                provider
                    ? ProviderController.connect(provider.id)
                    : ProviderController.store()
            }
            showProgress={false}
        >
            {({ errors, processing }) => (
                <>
                    <ModelModalHeader
                        provider={provider}
                        title={`Connecter le fournisseur : ${provider?.label || 'Un fournisseur personnalisé'}`}
                        onBack={onCancel}
                    />
                    {provider ? (
                        <>
                            <Input
                                label={`Clé d'API ${provider.label}`}
                                name="api_key"
                                error={errors.api_key}
                                placeholder="Coller la clé ici..."
                                description={`
                                Entrez votre clé API ${provider.label} pour connecter votre compte et utiliser les modèles ${provider.label} dans ${'OpenDB'}.
                            `}
                            />
                            {provider.id === 'ollama' && (
                                <Input
                                    label="Url de base"
                                    name="api_url"
                                    placeholder="Ex: https://api.monfournisseur.com/v1"
                                    error={errors.api_url}
                                    defaultValue={provider.api_url}
                                />
                            )}
                        </>
                    ) : (
                        <ConstomProviderFiledls />
                    )}
                    <div className="flex items-end justify-end gap-2 pt-4">
                        <Button
                            variant="secondary"
                            onClick={onCancel}
                            disabled={processing}
                        >
                            Anuler
                        </Button>
                        <Button
                            icon={PlugIcon}
                            type="submit"
                            processing={processing}
                        >
                            Connecter
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
};

export default ConnectProviderModal;
