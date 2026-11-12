import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChevronDownIcon, TriangleAlertIcon } from 'lucide-react';
import React, { useState } from 'react';
import CardFrame from '@/components/card-frame';
import { columnKey, useCloningStore } from '@/components/cloning/cloning-store';
import type { AvailableStrategy } from '@/components/cloning/cloning.types';
import StrategyOptions from '@/components/cloning/strategy-options/strategy-options';
import type { ColumnProps } from '@/components/cloning/strategy-options/types';
import { useCloningLabels } from '@/components/cloning/use-cloning-labels';
import Button from '@/components/form/button';
import Select from '@/components/form/select';
import { Badge } from '@/components/reui/badge';
import { Muted } from '@/components/typograpfy/typography';

const TablesAnonymisationColumn = ({ table, column }: ColumnProps) => {
    const [isVisibleOptions, setIsVisibleOptions] = useState(false);

    const key = columnKey(table, column);
    const meta = useCloningStore((s) => s.meta[key]);
    const transformation = useCloningStore((s) => s.values[key]);
    const setStrategy = useCloningStore((s) => s.setStrategy);
    const { t } = useLaravelReactI18n();
    const labels = useCloningLabels();

    return (
        <div className="flex flex-col gap-2 border-b border-border p-2 last:border-b-0">
            <div className="flex items-center justify-between gap-4">
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <div className="flex min-w-0 items-center gap-2">
                        <span className="truncate font-medium">{meta.name}</span>
                        <Badge variant="secondary" className="shrink-0">
                            {meta.type}
                        </Badge>
                    </div>
                    {meta.detectedMatcher && (
                        <Muted
                            className="flex min-w-0 items-center gap-1 text-xs text-warning"
                            title={t('cloning.ui.detected', { group: labels.group(meta.detectedMatcher.group), matcher: labels.matcher(meta.detectedMatcher.key) })}
                        >
                            <TriangleAlertIcon className="size-3 shrink-0" />
                            <span className="truncate">
                                {t('cloning.ui.detected', {
                                    group: labels.group(meta.detectedMatcher.group),
                                    matcher: labels.matcher(meta.detectedMatcher.key),
                                })}
                            </span>
                        </Muted>
                    )}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <Badge variant="outline" size="xl">
                        {labels.strategy(transformation.strategy)}
                    </Badge>
                    <Button
                        icon={ChevronDownIcon}
                        onClick={() => setIsVisibleOptions((v) => !v)}
                        size="icon-sm"
                        variant="secondary"
                        className={
                            isVisibleOptions
                                ? '[&_svg]:rotate-180 [&_svg]:transition-transform'
                                : '[&_svg]:transition-transform'
                        }
                    />
                </div>
            </div>
            {isVisibleOptions && (
                <CardFrame
                    className="rounded-lg"
                    innerClassName={'rounded-lg p-2 grid grid-cols-1 gap-2 sm:grid-cols-2'}
                >
                    <Select
                        label={t('cloning.ui.strategy')}
                        value={transformation.strategy}
                        onValueChange={(value) =>
                            setStrategy(table, column, value as AvailableStrategy)
                        }
                        items={meta.options.map((option) => ({
                            value: option,
                            label: labels.strategy(option),
                        }))}
                    />
                    <StrategyOptions
                        table={table}
                        column={column}
                        transformation={transformation}
                    />
                </CardFrame>
            )}
        </div>
    );
};

export default TablesAnonymisationColumn;
