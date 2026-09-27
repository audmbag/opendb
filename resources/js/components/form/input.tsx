import type {
    LucideIcon} from 'lucide-react';
import {
    EyeClosedIcon,
    EyeIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import Tooltip from '@/components/tooltip';
import {
    Field,
    FieldDescription,
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
    icon?: LucideIcon
};

export default function Input({
    label,
    error,
    description,
    button,
    className,
    type: inputType,
    icon: Icon,
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
            {label && (
                <FieldLabel htmlFor={props.name || props.id}>
                    {label}
                </FieldLabel>
            )}

            <ButtonGroup>
                <InputGroup>
                    {Icon && !error && (
                        <InputGroupAddon>
                            <Icon className={'size-4'} />
                        </InputGroupAddon>
                    )}
                    {error && (
                        <Tooltip value={error} variant="destructive">
                            <InputGroupAddon className="cursor-auto text-destructive">
                                <TriangleAlertIcon className={'size-4'} />
                            </InputGroupAddon>
                        </Tooltip>
                    )}
                    <InputGroupInput
                        id={props.name || props.id}
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
        </Field>
    );
}
