import { useVirtualizer } from '@tanstack/react-virtual';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { BrainIcon, SearchIcon, XIcon } from 'lucide-react';
import {
    useCallback,
    useDeferredValue,
    useMemo,
    useRef,
    useState,
} from 'react';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import ModelItem from '@/components/settings/model-item';
import type { Provider } from '@/types/models/provider';

/**
 * The models of a connected provider, searched and switched on or off one by one.
 */
export default function ProviderModels({ provider }: { provider: Provider }) {
    const { t } = useLaravelReactI18n();
    const parentRef = useRef<HTMLDivElement>(null);
    const [inputValue, setInputValue] = useState('');
    const deferredInputValue = useDeferredValue(inputValue);

    const [models, setModels] = useState(provider.models);

    const filteredTricks = useMemo(() => {
        if (!deferredInputValue) {
            return models;
        }

        const normalized = deferredInputValue.toLowerCase();

        return models.filter(
            (item) =>
                item.label.toLowerCase().includes(normalized) ||
                item.id.toLowerCase().includes(normalized),
        );
    }, [deferredInputValue, models]);

    // The compiler leaves this component unmemoized, as useVirtualizer() needs: the list is redrawn on every scroll anyway.
    // eslint-disable-next-line react-hooks/incompatible-library
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

    const handleModelChangeStatus = useCallback(
        (modelId: string, status: boolean) => {
            setModels((mdls) => {
                return mdls.map((model) => {
                    if (model.id === modelId) {
                        return { ...model, active: status };
                    }

                    return model;
                });
            });
        },
        [],
    );

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            <div className="flex items-center gap-4 p-2">
                <Input
                    placeholder={t('settings.models.search')}
                    value={inputValue}
                    onChange={(e) => onInputValueChange(e.target.value)}
                    icon={SearchIcon}
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
                            title={t('settings.models.no_models')}
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
                                    onToggle={(status) =>
                                        handleModelChangeStatus(
                                            model.id,
                                            status,
                                        )
                                    }
                                />
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
