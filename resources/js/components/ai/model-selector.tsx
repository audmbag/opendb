import { AstroidIcon, BrainIcon, CheckIcon, SettingsIcon } from 'lucide-react';
import React, { memo, useCallback, useEffect, useMemo, useState } from 'react';
import {
    ModelSelector as AIElementModelSelector,
    ModelSelectorContent,
    ModelSelectorEmpty,
    ModelSelectorGroup,
    ModelSelectorInput,
    ModelSelectorItem,
    ModelSelectorList,
    ModelSelectorName,
    ModelSelectorTrigger,
} from '@/components/ai-elements/model-selector';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import { useProvider } from '@/hooks/use-provider';
import { useSettingsDialog } from '@/hooks/use-settings-modal';
import { cn } from '@/lib/utils';
import type { AiModel, Provider } from '@/types/models/provider';

interface ModelItemProps {
    providerId: string;
    model: AiModel;
    selectedModel: { providerId: string, modelId: string };
    onSelect: (value: ModelItemProps['selectedModel']) => void;
}
export const ModelSelectorLogo = ({
    providerId,
    className,
    ...props
}: {
    providerId: string;
} & React.ComponentProps<'img'>) => (
    <img
        {...props}
        alt={`${providerId} logo`}
        className={cn('size-4', className)}
        height={12}
        src={`/assets/providers/${providerId}.svg`}
        width={12}
    />
);

const ModelItem = memo(({ providerId, model, selectedModel, onSelect }: ModelItemProps) => {
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
            <ModelSelectorLogo providerId={providerId} />
            <ModelSelectorName>{model.label}</ModelSelectorName>
            {selectedModel.modelId === model.id ? (
                <CheckIcon className="ml-auto size-4" />
            ) : (
                <div className="ml-auto size-4" />
            )}
        </ModelSelectorItem>
    );
});

ModelItem.displayName = 'ModelItem';

const ModelSelector = () => {
    const [open, setOpen] = useState(false);
    const [selectedModel, setSelectedModel] = useState<ModelItemProps['selectedModel']>({
        providerId: 'gemini',
        modelId: 'gemini-2.5-pro',
    });

    const { loadData, connectedProviders: providers, processing } = useProvider();

    const { open: openSettingsDialog } = useSettingsDialog()

    useEffect(() => {
        loadData();
    }, [loadData]);

    const handleModelSelect = useCallback(({ providerId, modelId }: ModelItemProps['selectedModel']) => {
        setSelectedModel({ providerId, modelId });
        setOpen(false);
    }, []);

    const selectedModelData = useMemo(() => {
        const provider = providers.find((provider) => provider.id === selectedModel.providerId)
        const model = provider?.models.find((model) => model.id === selectedModel.modelId)

        return {
            provider,
            model
        }
    }, [providers, selectedModel.modelId, selectedModel.providerId]);

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


    return (
        <AIElementModelSelector onOpenChange={setOpen} open={open}>
            <Button
                tooltip={['Choisir un mode', ['Ctrl', "'"]]}
                variant="ghost"
                processing={processing}
                onClick={handleOpen}
                size={"sm"}
            >
                <span className="flex items-center gap-1">
                    {selectedModelData?.provider ? (
                        <ModelSelectorLogo
                            providerId={selectedModelData.provider.id}
                        />
                    ) : <AstroidIcon className="size-4" />}
                    <ModelSelectorName>
                        {selectedModelData?.model?.label || "Configurer un fournisseur"}
                    </ModelSelectorName>
                </span>
            </Button>
            <ModelSelectorContent showCloseButton={false}>
                <div className="flex items-center gap-1 p-1 **:data-[slot=command-input-wrapper]:flex-1 **:data-[slot=command-input-wrapper]:p-0!">
                    <ModelSelectorInput
                        className="flex-1 p-0"
                        placeholder="Search models..."
                    />
                    <Button
                        variant="secondary"
                        tooltip="Gérer les modèles"
                        icon={SettingsIcon}
                        size="icon-sm"
                        onClick={openSettingsModal}
                    />
                </div>
                <ModelSelectorList>
                    <ModelSelectorEmpty asChild>
                        <EmptyState
                            title="Aucun modèle disponible"
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
