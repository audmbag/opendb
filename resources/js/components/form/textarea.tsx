import React from 'react';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';

import { Textarea as UITextarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Props = {
    label?: string;
    error?: string;
    description?: string;
} & React.ComponentPropsWithoutRef<typeof UITextarea>;

export default function Textarea({ label, error, description, className, ...props }: Props) {
    return (
        <Field className={cn('gap-2', className)}>
            {label && <FieldLabel htmlFor={props.id}>{label}</FieldLabel>}
            <UITextarea
                id={props.id}
                autoComplete="off"
                aria-invalid={!!error}
                {...props}
            />
            {description && <FieldDescription>{description}</FieldDescription>}
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
