import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';

import { ButtonGroup } from '../ui/button-group';
import Button, { ButtonProps } from './button';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '../ui/input-group';
import { EyeClosedIcon, EyeIcon, TriangleAlertIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

type Props = {
    label?: string;
    error?: string;
    description?: string;
    button?: ButtonProps;
} & React.ComponentPropsWithoutRef<typeof InputGroupInput>;

export default function Input({ label, error, description, button, className, type: inputType, ...props }: Props) {

    const [showPassword, setShowPassword] = useState(false);

    const type = useMemo(() => {
        if (inputType === "password") {
            return showPassword ? "text" : "password";
        }

        return inputType;
    }, [inputType, showPassword]);


    return (
        <Field className={className}>
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
                    <TriangleAlertIcon className={"size-4 inline-block -mt-1.5"} />
                    <span className="pl-1">{error}</span>
                </FieldError>
            )}
        </Field>
    );
}
