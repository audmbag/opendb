import { useLaravelReactI18n } from 'laravel-react-i18n';
import React from 'react';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type { StrategyOptionsProps } from '@/components/cloning/strategy-options/types';
import Select from '@/components/form/select';

/** Algorithmes acceptés par clonio (sa constante VALID_HASH_ALGORITHMS). */
const HASH_ALGORITHMS = [
    { value: 'sha256', label: 'SHA-256' },
    { value: 'sha512', label: 'SHA-512' },
    { value: 'md5', label: 'MD5' },
    { value: 'sha1', label: 'SHA-1' },
];

const HashOptions = ({ table, column, transformation }: StrategyOptionsProps) => {
    const { t } = useLaravelReactI18n();
    const setField = useCloningStore((s) => s.setField);

    if (transformation.strategy !== 'hash') {
        return null;
    }

    return (
        <Select
            label={t('cloning.ui.hash_algorithm')}
            value={transformation.algorithm}
            onValueChange={(value) => setField(table, column, 'algorithm', value)}
            items={HASH_ALGORITHMS}
        />
    );
};

export default HashOptions;
