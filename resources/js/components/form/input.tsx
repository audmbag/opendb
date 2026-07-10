import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';

import { Input as UIInput } from '@/components/ui/input';
import { ButtonGroup } from '../ui/button-group';
import Button, { ButtonProps } from './button';

type Props = {
    label?: string;
    error?: string;
    description?: string;
    button?: ButtonProps;
} & React.ComponentPropsWithoutRef<typeof UIInput>;

export default function Input({ label, error, description, button, className, ...props }: Props) {
    return (
        <Field className={className}>
            {label && <FieldLabel htmlFor={props.id}>{label}</FieldLabel>}

            <ButtonGroup>
                <UIInput id={props.id} autoComplete="off" aria-invalid={!!error} {...props} />
                {button && <Button {...button} />}
            </ButtonGroup>
            {description && <FieldDescription>{description}</FieldDescription>}
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
