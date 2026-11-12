import { useLaravelReactI18n } from 'laravel-react-i18n';
import React from 'react';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type { StrategyOptionsProps } from '@/components/cloning/strategy-options/types';
import Input from '@/components/form/input';

const StaticOptions = ({ table, column, transformation }: StrategyOptionsProps) => {
    const { t } = useLaravelReactI18n();
    const setField = useCloningStore((s) => s.setField);

    if (transformation.strategy !== 'static') {
        return null;
    }

    return (
        <Input
            label={t('cloning.ui.static_value')}
            type="text"
            value={transformation.value === null || transformation.value === undefined ? '' : String(transformation.value)}
            onChange={(e) => setField(table, column, 'value', e.target.value)}
        />
    );
};

export default StaticOptions;
