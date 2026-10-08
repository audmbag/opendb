import React, { useMemo } from 'react';
import { columnKey, useCloningStore } from '@/components/cloning/cloning-store';
import type { FakerMethodOption } from '@/components/cloning/cloning.types';
import FakerParameterField from '@/components/cloning/strategy-options/faker-parameter-field';
import type { StrategyOptionsProps } from '@/components/cloning/strategy-options/types';
import Select from '@/components/form/select';

const FakerOptions = ({ table, column, transformation }: StrategyOptionsProps) => {
    const applyFakerMethod = useCloningStore((s) => s.applyFakerMethod);
    const labels = useCloningStore((s) => s.labels);
    const meta = useCloningStore((s) => s.meta[columnKey(table, column)]);
    const catalog = useCloningStore((s) =>
        meta?.fakerCatalogKey ? s.fakerCatalogs[meta.fakerCatalogKey] : undefined,
    );

    const fakerOptions = useMemo<FakerMethodOption[]>(() => {
        const suggested = meta?.suggestedFakerMethod;
        const list = catalog ?? [];

        if (!suggested) {
            return list;
        }

        return [
            suggested,
            ...list.filter((m) => m.fakerMethod !== suggested.fakerMethod),
        ];
    }, [catalog, meta?.suggestedFakerMethod]);

    if (transformation.strategy !== 'fake') {
        return null;
    }

    const selected = fakerOptions.find(
        (m) => m.fakerMethod === transformation.fakerMethod,
    );

    return (
        <>
            <Select
                label="Méthode Faker"
                value={transformation.fakerMethod}
                onValueChange={(value) => {
                    const method = fakerOptions.find((m) => m.fakerMethod === value);

                    if (method) {
                        applyFakerMethod(table, column, method);
                    }
                }}
                items={fakerOptions.map((m) => {
                    const name = labels.fakerMethods[m.fakerMethod] ?? m.fakerMethod;

                    return {
                        value: m.fakerMethod,
                        label:
                            m.fakerMethod === meta?.suggestedFakerMethod?.fakerMethod
                                ? `${name} (suggéré)`
                                : name,
                    };
                })}
            />
            {selected?.parameters.map((parameter, index) =>
                parameter.editable ? (
                    <FakerParameterField
                        key={`${selected.fakerMethod}.${parameter.name}`}
                        table={table}
                        column={column}
                        parameter={parameter}
                        index={index}
                        value={transformation.fakerArguments[index]}
                    />
                ) : null,
            )}
        </>
    );
};

export default FakerOptions;
