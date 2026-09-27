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
            onlyFolders
        });

        if (file) {
            updateValue(file.id);
        }
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        updateValue(e.target.value);
    };

    return (
        <Input
            {...props}
            label={`Sélectionner un ${onlyFolders ? 'dossier' : 'fichier'}`}
            placeholder={placeholder || `Sélectionner un ${onlyFolders ? 'dossier' : 'fichier'}`}
            value={value}
            onChange={handleChange}
            button={{
                variant: 'outline',
                icon: FolderIcon,
                tooltip: `Sélectionner un ${onlyFolders ? 'dossier' : 'fichier'}`,
                onClick: selectFile,
            }}
        />
    );
}
