import React from 'react';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldLabel,
} from '@/components/ui/field';
import { Switch as UISwitch } from '@/components/ui/switch';

type Props = {
    label?: string;
    error?: string;
    description?: string;
    orientation?: React.ComponentProps<typeof Field>['orientation'];
} & React.ComponentProps<typeof UISwitch>;

export default function Switch({ label, error, description, className, orientation, ...props }: Props) {
    return (
        <Field className={className} data-invalid={!!error} orientation={orientation}>
            <FieldContent>
                {label && (
                    <FieldLabel htmlFor={props.name || props.id}>
                        {label}
                    </FieldLabel>
                )}
                {description && (
                    <FieldDescription>{description}</FieldDescription>
                )}
            </FieldContent>
            <UISwitch id={props.name || props.id} {...props} />
        </Field>
    );
}
