import React from 'react';
import { useCloningStore } from '@/components/cloning/cloning-store';
import type { FakerParameter } from '@/components/cloning/cloning.types';
import type { ColumnProps } from '@/components/cloning/strategy-options/types';
import { useCloningLabels } from '@/components/cloning/use-cloning-labels';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import Switch from '@/components/form/switch';

const FakerParameterField = ({
    table,
    column,
    parameter,
    index,
    value,
}: ColumnProps & {
    parameter: FakerParameter;
    index: number;
    value: unknown;
}) => {
    const setFakerArgument = useCloningStore((s) => s.setFakerArgument);
    const labels = useCloningLabels();
    const label = labels.fakerParameter(parameter.name);
    const current = value ?? parameter.default;

    if (parameter.type === 'boolean') {
        return (
            <Switch
                label={label}
                checked={Boolean(current)}
                onCheckedChange={(checked) =>
                    setFakerArgument(table, column, index, checked)
                }
            />
        );
    }

    if (parameter.type === 'enum') {
        return (
            <Select
                label={label}
                value={current === null ? '' : String(current)}
                onValueChange={(v) => setFakerArgument(table, column, index, v)}
                items={(parameter.options ?? []).map((option) => ({
                    value: option,
                    label: labels.fakerParameterOption(option),
                }))}
            />
        );
    }

    if (parameter.type === 'integer' || parameter.type === 'float') {
        return (
            <Input
                label={label}
                type="number"
                min={parameter.min ?? undefined}
                max={parameter.max ?? undefined}
                step={parameter.type === 'integer' ? 1 : 'any'}
                value={current === null || current === undefined ? '' : String(current)}
                onChange={(e) => {
                    const raw = e.target.value;

                    setFakerArgument(
                        table,
                        column,
                        index,
                        raw === '' ? (parameter.nullable ? null : parameter.default) : Number(raw),
                    );
                }}
            />
        );
    }

    return (
        <Input
            label={label}
            type="text"
            value={current === null || current === undefined ? '' : String(current)}
            onChange={(e) =>
                setFakerArgument(
                    table,
                    column,
                    index,
                    e.target.value === '' && parameter.nullable ? null : e.target.value,
                )
            }
        />
    );
};

export default FakerParameterField;
