import React from 'react';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type { StrategyOptionsProps } from '@/components/cloning/strategy-options/types';
import Input from '@/components/form/input';
import Switch from '@/components/form/switch';

const MaskOptions = ({ table, column, transformation }: StrategyOptionsProps) => {
    const setField = useCloningStore((s) => s.setField);

    if (transformation.strategy !== 'mask') {
        return null;
    }

    return (
        <>
            <Input
                label="Caractères visibles"
                type="number"
                min={0}
                value={transformation.visibleChars}
                onChange={(e) =>
                    setField(table, column, 'visibleChars', Number(e.target.value))
                }
            />
            <Input
                label="Caractère de masque"
                type="text"
                maxLength={1}
                value={transformation.maskChar}
                onChange={(e) => setField(table, column, 'maskChar', e.target.value)}
            />
            <Switch
                label="Préserver le format"
                checked={transformation.preserveFormat}
                onCheckedChange={(checked) =>
                    setField(table, column, 'preserveFormat', checked)
                }
            />
        </>
    );
};

export default MaskOptions;
