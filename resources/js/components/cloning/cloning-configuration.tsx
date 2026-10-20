import React, { useImperativeHandle, useState } from 'react';
import CardFrame from '@/components/card-frame';
import type { CloningConfigurationData } from '@/components/cloning/cloning.types';
import Switch from '@/components/form/switch';
import type { ProjectPartFromRef } from '@/hooks/use-project';


export type CloningConfigurationFormRef = ProjectPartFromRef<CloningConfigurationData>;

type Props = {
    ref?: React.Ref<CloningConfigurationFormRef>;
};

type CloningSwitchOption = keyof CloningConfigurationData;

export const CLONING_SWITCH_OPTIONS: { name: CloningSwitchOption; label: string }[] = [
    { name: 'enforce_column_types', label: 'Forcer les types de colonnes' },
    { name: 'disable_fk_checks', label: 'Désactiver les vérifications de clés étrangères' },
    { name: 'drop_unknown_tables', label: 'Supprimer les tables inconnues' },
    { name: 'drop_extra_columns', label: 'Supprimer les colonnes supplémentaires' },
    { name: 'break_on_failure', label: 'Arrêter le clonage à la première erreur' },
];

export default function CloningConfiguration({ ref }: Props) {
    const [data, setFormData] = useState<CloningConfigurationData>(() => ({
        enforce_column_types: true,
        drop_unknown_tables: false,
        drop_extra_columns: false,
        disable_fk_checks: true,
        break_on_failure: false,
    }));

    const setData = <K extends keyof CloningConfigurationData>(key: K, value: CloningConfigurationData[K]) => {
        setFormData((prev) => ({ ...prev, [key]: value }));
    };

    useImperativeHandle(ref, () => ({
        submit: () => data,
    }), [data]);

    return (
        <CardFrame innerPadding={false}>
            {CLONING_SWITCH_OPTIONS.map(({ name, label }) => (
                <Switch
                    key={name}
                    orientation="horizontal"
                    label={label}
                    name={name}
                    className="border-b border-border p-3 last:border-b-0"
                    checked={data[name]}
                    onCheckedChange={(checked) => setData(name, checked)}
                />
            ))}
        </CardFrame>
    );
}
