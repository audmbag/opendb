import type { FormDataErrors } from '@inertiajs/core';
import { Form } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { PlugIcon } from 'lucide-react';
import { Fragment } from 'react';
import { toast } from 'sonner';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import KeyValue from '@/components/form/key-value';
import type { Provider } from '@/types/models/provider';
import ProviderController from '@/wayfinder/actions/App/Http/Controllers/ProviderController';

const CustomProviderFields = ({
    errors,
}: {
    errors: FormDataErrors<Record<string, any>>;
}) => {
    const { t } = useLaravelReactI18n();

    return (
        <Fragment>
            <Input
                label={t('settings.providers.id')}
                name="id"
                placeholder={t('settings.providers.id_placeholder')}
                description={t('settings.providers.id_description')}
                error={errors.id}
            />
            <Input
                label={t('settings.providers.label')}
                name="label"
                placeholder={t('settings.providers.label_placeholder')}
                error={errors.label}
            />
            <Input
                label={t('settings.providers.api_url')}
                name="api_url"
                placeholder={t('settings.providers.api_url_placeholder')}
                error={errors.api_url}
            />
            <Input
                label={t('settings.providers.api_key')}
                name="api_key"
                placeholder={t('settings.providers.api_key_placeholder')}
                error={errors.api_key}
            />
            <KeyValue
                label={t('settings.providers.models')}
                description={t('settings.providers.models_description')}
                name={'models'}
                keyPlaceholder={t('settings.providers.model_id_placeholder')}
                valuePlaceholder={t(
                    'settings.providers.model_label_placeholder',
                )}
                addLabel={t('settings.providers.add_model')}
                minItems={1}
                error={errors.models}
            />
        </Fragment>
    );
};

/**
 * The key of a provider, or the address and the models of a custom one, shown as a page of the models settings.
 */
const ConnectProviderForm = ({
    provider,
    onCancel,
    onSuccess,
}: {
    provider?: Provider;
    onCancel: () => void;
    onSuccess: () => void;
}) => {
    const { t } = useLaravelReactI18n();
    const isOllama = provider?.id === 'ollama';

    return (
        <Form
            className="flex flex-col gap-4"
            onSuccess={(page) => {
                if (page.flash.error) {
                    toast.error(page.flash.error as string, {
                        duration: 10000,
                    });

                    return;
                }

                toast.success(page.flash.success as string);
                onSuccess();
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
                    {provider ? (
                        <>
                            <Input
                                label={t(
                                    isOllama
                                        ? 'settings.providers.optional_api_key'
                                        : 'settings.providers.provider_api_key',
                                    { provider: provider.label },
                                )}
                                name="api_key"
                                error={errors.api_key}
                                placeholder={t('settings.providers.paste_key')}
                                description={t(
                                    isOllama
                                        ? 'settings.providers.api_key_optional_description'
                                        : 'settings.providers.api_key_description',
                                    { provider: provider.label },
                                )}
                            />
                            {isOllama && (
                                <Input
                                    label={t('settings.providers.api_url')}
                                    name="api_url"
                                    placeholder={t(
                                        'settings.providers.api_url_placeholder',
                                    )}
                                    error={errors.api_url}
                                    defaultValue={provider.api_url}
                                />
                            )}
                        </>
                    ) : (
                        <CustomProviderFields errors={errors} />
                    )}
                    <div className="flex items-end justify-end gap-2 pt-2">
                        <Button
                            variant="secondary"
                            onClick={onCancel}
                            disabled={processing}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            icon={PlugIcon}
                            type="submit"
                            processing={processing}
                        >
                            {t('settings.models.connect')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
};

export default ConnectProviderForm;
