import { useLaravelReactI18n } from 'laravel-react-i18n';
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

/**
 * The switches of the cloning, their label being a key of lang/{locale}/cloning.php.
 */
export const CLONING_SWITCH_OPTIONS: { name: CloningSwitchOption; label: string }[] = [
    { name: 'enforce_column_types', label: 'cloning.ui.options.enforce_column_types' },
    { name: 'disable_fk_checks', label: 'cloning.ui.options.disable_fk_checks' },
    { name: 'drop_unknown_tables', label: 'cloning.ui.options.drop_unknown_tables' },
    { name: 'drop_extra_columns', label: 'cloning.ui.options.drop_extra_columns' },
    { name: 'break_on_failure', label: 'cloning.ui.options.break_on_failure' },
];

export default function CloningConfiguration({ ref }: Props) {
    const { t } = useLaravelReactI18n();
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
                    label={t(label)}
                    name={name}
                    className="border-b border-border p-3 last:border-b-0"
                    checked={data[name]}
                    onCheckedChange={(checked) => setData(name, checked)}
                />
            ))}
        </CardFrame>
    );
}
