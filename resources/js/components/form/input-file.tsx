import { useLaravelReactI18n } from 'laravel-react-i18n';
import { FolderIcon } from 'lucide-react';
import React, { useCallback, useState } from 'react';
import Input from '@/components/form/input';
import SelectFileModal from '@/components/modals/select-file-modal';

type Props = {
    onlyFolders?: boolean;
    targetFileExtensions?: string[];
    onChange?: (value: string) => void;
    value?: string;
    defaultValue?: string;
} & Omit<React.ComponentPropsWithoutRef<typeof Input>, 'button' | 'label' | 'onChange' | 'value' | 'defaultValue'>;

export default function InputFile({
  onlyFolders,
  targetFileExtensions,
  value: controlledValue,
  defaultValue,
  onChange,
  placeholder,
  ...props
}: Props) {
    const { t } = useLaravelReactI18n();
    const [isControlled] = useState(() => controlledValue !== undefined);
    const [internalValue, setInternalValue] = useState(defaultValue ?? '');

    const value = isControlled ? (controlledValue as string) : internalValue;

    const updateValue = useCallback((newValue: string) => {
        if (!isControlled) {
            setInternalValue(newValue);
        }

        onChange?.(newValue);
    }, [isControlled, onChange]);

    const selectFile = async () => {
        const file = await SelectFileModal.call({
            targetFileExtensions,
            onlyFolders,
            initialPath: value || undefined,
        });

        if (file) {
            updateValue(file.id);
        }
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        updateValue(e.target.value);
    };

    const selectLabel = onlyFolders ? t('files.select_folder') : t('files.select_file');

    return (
        <Input
            {...props}
            label={selectLabel}
            placeholder={placeholder || selectLabel}
            value={value}
            onChange={handleChange}
            button={{
                variant: 'outline',
                icon: FolderIcon,
                tooltip: selectLabel,
                onClick: selectFile,
            }}
        />
    );
}
