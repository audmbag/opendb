import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AstroidIcon, BrainIcon, CheckIcon, SettingsIcon } from 'lucide-react';
import React, { memo, useCallback, useEffect, useMemo, useState } from 'react';
import { useHotkeys } from 'react-hotkeys-hook';
import {
    ModelSelector as AIElementModelSelector,
    ModelSelectorContent,
    ModelSelectorEmpty,
    ModelSelectorGroup,
    ModelSelectorInput,
    ModelSelectorItem,
    ModelSelectorList,
    ModelSelectorName,
} from '@/components/ai-elements/model-selector';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import type { ButtonProps } from '@/components/form/button';
import { useProvider } from '@/hooks/use-provider';
import { useSettingsDialog } from '@/hooks/use-settings-modal';
import { PROMPT_HOTKEY_OPTIONS } from '@/lib/hotkeys';
import { cn } from '@/lib/utils';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';
import type { SelectedModel } from '@/store/use-agent-settings-store';
import { useProviderStore } from '@/store/use-provider-store';
import type { AiModel, Provider } from '@/types/models/provider';

interface ModelItemProps {
    providerId: string;
    providerIcon: string;
    model: AiModel;
    selectedModel: SelectedModel | null;
    onSelect: (value: SelectedModel) => void;
}
export const ModelSelectorLogo = ({
    providerId,
    icon,
    className,
    ...props
}: {
    providerId: string;
    icon: string;
} & React.ComponentProps<'img'>) => (
    <img
        {...props}
        alt={`${providerId} logo`}
        className={cn('size-4', className)}
        height={12}
        src={icon}
        width={12}
    />
);

const ModelItem = memo(({ providerId, providerIcon, model, selectedModel, onSelect }: ModelItemProps) => {
    const handleSelect = useCallback(
        () => onSelect({ providerId, modelId: model.id }),
        [onSelect, providerId, model.id],
    );

    return (
        <ModelSelectorItem
            key={model.id}
            onSelect={handleSelect}
            value={model.id}
        >
            <ModelSelectorLogo providerId={providerId} icon={providerIcon} />
            <ModelSelectorName>{model.label}</ModelSelectorName>
            {selectedModel?.providerId === providerId && selectedModel.modelId === model.id ? (
                <CheckIcon className="ml-auto size-4" />
            ) : (
                <div className="ml-auto size-4" />
            )}
        </ModelSelectorItem>
    );
});

ModelItem.displayName = 'ModelItem';

const ModelSelector = ({ size = 'default' }: { size?: ButtonProps['size'] }) => {
    const { t } = useLaravelReactI18n();
    const [open, setOpen] = useState(false);
    const selectedModel = useAgentSettingsStore((state) => state.selectedModel);
    const setSelectedModel = useAgentSettingsStore((state) => state.setSelectedModel);
    const hasLoadedProviders = useProviderStore((state) => state.providers.length > 0);

    const { loadData, connectedProviders: providers, processing } = useProvider();

    const { open: openSettingsDialog } = useSettingsDialog()

    useEffect(() => {
        loadData();
    }, [loadData]);

    const handleModelSelect = useCallback((model: SelectedModel) => {
        setSelectedModel(model);
        setOpen(false);
    }, [setSelectedModel]);

    const selectedModelData = useMemo(() => {
        const provider = providers.find((provider) => provider.id === selectedModel?.providerId)
        const model = provider?.models.find((model) => model.id === selectedModel?.modelId && model.active)

        return {
            provider,
            model
        }
    }, [providers, selectedModel?.modelId, selectedModel?.providerId]);

    const normalizedPoviders = useMemo(() => {
        const ps: Provider[] = []

        structuredClone(providers).forEach((prov) => {
            prov.models = prov.models.filter((model) => model.active);

            if (prov.models.length > 0) {
                ps.push(prov);
            }
        });

        return ps;
    }, [providers]);

    /**
     * The server refuses a model that is no longer usable, so a remembered one is replaced as soon as the providers tell it is gone.
     */
    useEffect(() => {
        if (!hasLoadedProviders || selectedModelData.model) {
            return;
        }

        const firstProvider = normalizedPoviders[0];

        setSelectedModel(firstProvider ? { providerId: firstProvider.id, modelId: firstProvider.models[0].id } : null);
    }, [hasLoadedProviders, normalizedPoviders, selectedModelData.model, setSelectedModel]);

    const openSettingsModal = useCallback(() => {
        setOpen(false)
        openSettingsDialog('models');
    }, [openSettingsDialog]);


    const handleOpen = useCallback(() => {
        if (!selectedModelData.provider) {
            openSettingsDialog('models');
        } else {
            setOpen(true);
        }
    }, [openSettingsDialog, selectedModelData]);

    useHotkeys("mod+'", () => (open ? setOpen(false) : handleOpen()), PROMPT_HOTKEY_OPTIONS, [open, handleOpen]);


    return (
        <AIElementModelSelector onOpenChange={setOpen} open={open}>
            <Button
                tooltip={[t('shortcuts.model'), ['Ctrl', "'"]]}
                variant="secondary"
                processing={processing}
                onClick={handleOpen}
                size={size}
            >
                <span className="flex items-center gap-1">
                    {selectedModelData?.provider ? (
                        <ModelSelectorLogo
                            providerId={selectedModelData.provider.id}
                            icon={selectedModelData.provider.icon}
                            className={cn({ 'size-3': size === 'xs' })}
                        />
                    ) : <AstroidIcon className={size === 'xs' ? 'size-3' : 'size-4'} />}
                    <ModelSelectorName>
                        {selectedModelData?.model?.label || t('ai.models.configure_provider')}
                    </ModelSelectorName>
                </span>
            </Button>
            <ModelSelectorContent showCloseButton={false} title={t('ai.models.selector')}>
                <div className="flex items-center gap-1 p-1 **:data-[slot=command-input-wrapper]:flex-1 **:data-[slot=command-input-wrapper]:p-0!">
                    <ModelSelectorInput
                        className="flex-1 p-0"
                        placeholder={t('settings.models.search')}
                    />
                    <Button
                        variant="secondary"
                        tooltip={t('settings.models.manage_models')}
                        icon={SettingsIcon}
                        size="icon-sm"
                        onClick={openSettingsModal}
                    />
                </div>
                <ModelSelectorList>
                    <ModelSelectorEmpty asChild>
                        <EmptyState
                            title={t('ai.models.none')}
                            icon={BrainIcon}
                        />
                    </ModelSelectorEmpty>
                    {normalizedPoviders.map((provider) => (
                        <ModelSelectorGroup
                            heading={provider.label}
                            key={provider.id}
                        >
                            {provider.models.map((model) => (
                                <ModelItem
                                    providerId={provider.id}
                                    providerIcon={provider.icon}
                                    key={model.id}
                                    model={model}
                                    onSelect={handleModelSelect}
                                    selectedModel={selectedModel}
                                />
                            ))}
                        </ModelSelectorGroup>
                    ))}
                </ModelSelectorList>
            </ModelSelectorContent>
        </AIElementModelSelector>
    );
};

export default ModelSelector;
