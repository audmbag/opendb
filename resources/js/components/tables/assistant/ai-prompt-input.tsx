import { ArrowUpIcon } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useId, useState } from 'react';
import ModelSelector from '@/components/ai/model-selector';
import Button from '@/components/form/button';
import { FieldError } from '@/components/ui/field';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
    placeholder: string;
    label: string;
    /**
     * Sends the request and tells whether it succeeded: the field is emptied only then.
     */
    onSubmit: (prompt: string) => Promise<boolean>;
    processing: boolean;
    error: string | null;
    /**
     * Why the assistant cannot be asked here, shown over the field.
     */
    disabledReason?: string | null;
    autoFocus?: boolean;
    className?: string;
};

/**
 * A request to the assistant on one line, with the model it runs on: filtering the grid, writing a query…
 */
export default function AiPromptInput({
    icon: Icon,
    placeholder,
    label,
    onSubmit,
    processing,
    error,
    disabledReason = null,
    autoFocus = false,
    className,
}: Props) {
    const [prompt, setPrompt] = useState('');
    const errorId = useId();

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();

        if (prompt.trim() === '' || processing) {
            return;
        }

        if (await onSubmit(prompt.trim())) {
            setPrompt('');
        }
    };

    return (
        <form
            onSubmit={submit}
            title={disabledReason ?? undefined}
            className={cn('flex min-w-0 flex-1 flex-col gap-1', className)}
        >
            <InputGroup className="h-8">
                <InputGroupAddon>
                    <Icon aria-hidden />
                </InputGroupAddon>
                <InputGroupInput
                    value={prompt}
                    onChange={(event) => setPrompt(event.target.value)}
                    disabled={disabledReason !== null}
                    maxLength={500}
                    placeholder={placeholder}
                    aria-label={label}
                    aria-invalid={error !== null}
                    aria-describedby={error ? errorId : undefined}
                    autoFocus={autoFocus}
                />
                <InputGroupAddon align="inline-end">
                    <ModelSelector size="xs" />
                    <Button
                        type="submit"
                        size="icon-xs"
                        variant="ghost"
                        disabled={
                            disabledReason !== null ||
                            prompt.trim() === '' ||
                            processing
                        }
                        aria-label={label}
                        tooltip={label}
                        icon={ArrowUpIcon}
                        processing={processing}
                    />
                </InputGroupAddon>
            </InputGroup>
            {error && (
                <FieldError id={errorId} className="px-1 text-xs">
                    {error}
                </FieldError>
            )}
        </form>
    );
}
