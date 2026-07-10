import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';

import { Textarea as UITextarea } from '@/components/ui/textarea';

type Props = {
    label?: string;
    error?: string;
    description?: string;
} & React.ComponentPropsWithoutRef<typeof UITextarea>;

export default function Textarea({ label, error, description, className, ...props }: Props) {
    return (
        <Field className={className}>
            {label && <FieldLabel htmlFor={props.id}>{label}</FieldLabel>}
            <UITextarea id={props.id} autoComplete="off" aria-invalid={!!error} {...props} />
            {description && <FieldDescription>{description}</FieldDescription>}
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
