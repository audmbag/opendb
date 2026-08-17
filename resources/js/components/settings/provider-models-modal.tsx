import { useVirtualizer } from '@tanstack/react-virtual';
import {
    BrainIcon,
    SearchIcon,
    XIcon,
} from 'lucide-react';
import React, {
    useCallback,
    useDeferredValue,
    useMemo,
    useRef,
    useState,
} from 'react';
import { createCallable } from 'react-call';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Modal from '@/components/modal';
import ModelItem from '@/components/settings/model-item';
import ModelModalHeader from '@/components/settings/model-modal-header';
import type { Provider } from '@/types/models/provider';

const ProviderModelsContainer = ({ provider }: { provider: Provider }) => {
    const parentRef = useRef<HTMLDivElement>(null);
    const [inputValue, setInputValue] = useState('');
    const deferredInputValue = useDeferredValue(inputValue);

    const filteredTricks = useMemo(() => {
        if (!deferredInputValue) {
            return provider.models;
        }

        const normalized = deferredInputValue.toLowerCase();

        return provider.models.filter(
            (item) =>
                item.label.toLowerCase().includes(normalized) ||
                item.id.toLowerCase().includes(normalized),
        );
    }, [deferredInputValue, provider.models]);

    const virtualizer = useVirtualizer({
        count: filteredTricks.length,
        getScrollElement: () => parentRef.current,
        estimateSize: () => 40,
        overscan: 5,
    });

    const onInputValueChange = useCallback((value: string) => {
        setInputValue(value);

        if (parentRef.current) {
            parentRef.current.scrollTop = 0;
        }
    }, []);

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            <div className="flex items-center gap-4 p-2">
                <SearchIcon className="size-5 shrink-0 text-muted-foreground" />
                <Input
                    placeholder="Rechercher un modèle..."
                    value={inputValue}
                    onChange={(e) => onInputValueChange(e.target.value)}
                />
                {!!inputValue && (
                    <Button
                        variant={'secondary'}
                        icon={XIcon}
                        onClick={() => onInputValueChange('')}
                    />
                )}
            </div>
            <div
                ref={parentRef}
                className="flex flex-1 flex-col overflow-x-hidden overflow-y-auto p-2"
            >
                <div
                    style={{
                        height: `${virtualizer.getTotalSize()}px`,
                        width: '100%',
                        position: 'relative',
                    }}
                >
                    {virtualizer.getVirtualItems().length === 0 && (
                        <EmptyState
                            title="Aucun modèle trouvé"
                            icon={BrainIcon}
                        />
                    )}
                    {virtualizer.getVirtualItems().map((virtualItem) => {
                        const model = filteredTricks[virtualItem.index];

                        if (!model) {
                            return null;
                        }

                        return (
                            <div
                                key={virtualItem.key}
                                data-index={virtualItem.index}
                                ref={virtualizer.measureElement}
                                style={{
                                    position: 'absolute',
                                    top: 0,
                                    left: 0,
                                    width: '100%',
                                    transform: `translateY(${virtualItem.start}px)`,
                                }}
                            >
                                <ModelItem
                                    model={model}
                                    providerId={provider.id}
                                />
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
};

export const ProviderModelsModal = createCallable<{ provider: Provider }>(
    ({ provider, call }) => {
        return (
            <Modal
                open={!call.ended}
                onOpenChange={() => call.end()}
                className="h-170 sm:max-w-170"
                description={`Gérer les modèles du provider : ${provider.label}`}
                title={provider.label}
            >
                <div className="flex h-full flex-col gap-4 overflow-hidden p-2">
                    <ModelModalHeader
                        provider={provider}
                        title={`Gérer les modèles de ${provider.label}`}
                        onBack={() => call.end()}
                    />
                    <ProviderModelsContainer provider={provider} />
                    <div className="flex justify-end">
                        <Button onClick={() => call.end()} variant="secondary">
                            Fermer
                        </Button>
                    </div>
                </div>
            </Modal>
        );
    },
    1000,
);
