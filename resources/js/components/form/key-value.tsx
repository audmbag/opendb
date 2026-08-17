import { PlusIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react';
import React from 'react';
import Button from '@/components/form/button';
import type { InputPropsType } from '@/components/form/input';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import {
    KeyValue as UiKeyValue,
    KeyValueAdd,
    KeyValueItem,
    KeyValueKeyInput,
    KeyValueList,
    KeyValueRemove,
    KeyValueValueInput,
} from "@/components/ui/key-value";
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';


type Props = {
    addLabel?: string;
} & Omit<InputPropsType, 'button'> & React.ComponentProps<typeof UiKeyValue>;

export default function KeyValue({
    label,
    description,
    error,
    addLabel,
    className,
    ...props
}: Props) {
    return (
        <Field className={cn('gap-2', className)}>
            {label && <FieldLabel htmlFor={props.id}>{label}</FieldLabel>}
            {description && <FieldDescription>{description}</FieldDescription>}
            <UiKeyValue {...props} allowDuplicateKeys={false}>
                <KeyValueList>
                    <KeyValueItem className="flex-col items-start">
                        <div className="flex w-full gap-2">
                            <KeyValueKeyInput />
                            <KeyValueValueInput asChild>
                                <Input />
                            </KeyValueValueInput>
                            <KeyValueRemove asChild variant="destructive">
                                <Button size="icon" icon={Trash2Icon} />
                            </KeyValueRemove>
                        </div>
                    </KeyValueItem>
                </KeyValueList>
                <div className="flex justify-start">
                    <KeyValueAdd asChild variant="secondary">
                        <Button icon={PlusIcon}>
                            {addLabel || 'Ajouter un item'}
                        </Button>
                    </KeyValueAdd>
                </div>
            </UiKeyValue>
            {error && (
                <FieldError>
                    <TriangleAlertIcon
                        className={'-mt-1.5 inline-block size-4'}
                    />
                    <span className="pl-1">{error}</span>
                </FieldError>
            )}
        </Field>
    );
}
