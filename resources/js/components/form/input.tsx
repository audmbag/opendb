import { EyeClosedIcon, EyeIcon, TriangleAlertIcon } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';

import { cn } from '@/lib/utils';
import { ButtonGroup } from '../ui/button-group';
import { InputGroup, InputGroupAddon, InputGroupInput } from '../ui/input-group';
import type { ButtonProps } from './button';
import Button from './button';

export type InputPropsType = {
    label?: string;
    error?: string;
    description?: string;
    button?: ButtonProps;
};

export default function Input({
    label,
    error,
    description,
    button,
    className,
    type: inputType,
    ...props
}: InputPropsType & React.ComponentPropsWithoutRef<typeof InputGroupInput>) {
    const [showPassword, setShowPassword] = useState(false);

    const type = useMemo(() => {
        if (inputType === 'password') {
            return showPassword ? 'text' : 'password';
        }

        return inputType;
    }, [inputType, showPassword]);

    return (
        <Field className={cn('gap-2', className)}>
            {label && <FieldLabel htmlFor={props.id}>{label}</FieldLabel>}

            <ButtonGroup>
                <InputGroup>
                    <InputGroupInput
                        id={props.id}
                        name={props.name || props.id}
                        type={type}
                        autoComplete="off"
                        aria-invalid={!!error}
                        {...props}
                    />
                    {inputType === 'password' && (
                        <InputGroupAddon
                            onClick={() => setShowPassword((prev) => !prev)}
                            className="cursor-pointer"
                            align={'inline-end'}
                        >
                            {showPassword ? <EyeIcon /> : <EyeClosedIcon />}
                        </InputGroupAddon>
                    )}
                </InputGroup>
                {button && <Button type="button" {...button} />}
            </ButtonGroup>
            {description && <FieldDescription>{description}</FieldDescription>}
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
