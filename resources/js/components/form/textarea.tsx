import React from 'react';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';

import {
    InputGroup,
    InputGroupAddon,
    InputGroupText,
    InputGroupTextarea,
} from '@/components/ui/input-group';
import { cn } from '@/lib/utils';
import { FileCodeIcon, LucideIcon, TriangleAlertIcon } from 'lucide-react';
import Tooltip from '@/components/tooltip';

type Props = {
    label?: string;
    error?: string;
    description?: string;
    icon?: LucideIcon;
} & React.ComponentPropsWithoutRef<typeof InputGroupTextarea>;

export default function Textarea({ label, error, description, className, placeholder, icon: Icon, ...props }: Props) {
    return (
        <Field className={cn('gap-2', className)}>
            {label && (
                <FieldLabel htmlFor={props.name || props.id}>
                    {label}
                </FieldLabel>
            )}
            <InputGroup>
                <InputGroupTextarea
                    id={props.name || props.id}
                    autoComplete="off"
                    aria-invalid={!!error}
                    {...props}
                />
                {(Icon || placeholder || error) && (
                    <InputGroupAddon align="block-start">
                        {Icon && !error && (
                            <Icon className="text-muted-foreground" />
                        )}
                        {error && (
                            <Tooltip value={error} variant="destructive">
                                <TriangleAlertIcon
                                    className={
                                        'size-4 cursor-auto text-destructive'
                                    }
                                />
                            </Tooltip>
                        )}
                        <InputGroupText>{placeholder}</InputGroupText>
                    </InputGroupAddon>
                )}
            </InputGroup>
            {description && <FieldDescription>{description}</FieldDescription>}
            {/*{error && (*/}
            {/*    <FieldError>*/}
            {/*        <TriangleAlertIcon*/}
            {/*            className={'-mt-1.5 inline-block size-4'}*/}
            {/*        />*/}
            {/*        <span className="pl-1">{error}</span>*/}
            {/*    </FieldError>*/}
            {/*)}*/}
        </Field>
    );
}
