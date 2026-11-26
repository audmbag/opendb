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
    InputGroupTextarea,
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
    /**
     * The longest request the server accepts.
     */
    maxLength?: number;
    /**
     * Written on several lines, sent with Enter while Shift+Enter starts a new line.
     */
    multiline?: boolean;
    /**
     * The request, when the caller keeps it, like a popover that keeps the draft once closed.
     */
    prompt?: string;
    onPromptChange?: (prompt: string) => void;
    className?: string;
};

/**
 * A request to the assistant, with the model it runs on: filtering the grid, writing a query…
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
    maxLength = 500,
    multiline = false,
    prompt: ownedPrompt,
    onPromptChange,
    className,
}: Props) {
    const [localPrompt, setLocalPrompt] = useState('');
    const prompt = ownedPrompt ?? localPrompt;
    const setPrompt = (next: string) => {
        setLocalPrompt(next);
        onPromptChange?.(next);
    };
    const errorId = useId();

    const send = async () => {
        if (prompt.trim() === '' || processing) {
            return;
        }

        if (await onSubmit(prompt.trim())) {
            setPrompt('');
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        void send();
    };

    const sendOnEnter = (event: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing
        ) {
            event.preventDefault();
            void send();
        }
    };

    const fieldProps = {
        value: prompt,
        disabled: disabledReason !== null,
        maxLength,
        placeholder,
        'aria-label': label,
        'aria-invalid': error !== null,
        'aria-describedby': error ? errorId : undefined,
        autoFocus,
    };

    const actions = (
        <>
            <ModelSelector size="xs" />
            <Button
                type="submit"
                size="icon-xs"
                variant={multiline ? 'default' : 'ghost'}
                className={cn(multiline && 'ml-auto')}
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
        </>
    );

    return (
        <form
            onSubmit={submit}
            title={disabledReason ?? undefined}
            className={cn('flex min-w-0 flex-1 flex-col gap-1', className)}
        >
            {multiline ? (
                <InputGroup>
                    <InputGroupTextarea
                        {...fieldProps}
                        onChange={(event) => setPrompt(event.target.value)}
                        onKeyDown={sendOnEnter}
                        rows={3}
                        className="max-h-48 min-h-20"
                    />
                    <InputGroupAddon align="block-end">
                        {actions}
                    </InputGroupAddon>
                </InputGroup>
            ) : (
                <InputGroup className="h-8">
                    <InputGroupAddon>
                        <Icon aria-hidden />
                    </InputGroupAddon>
                    <InputGroupInput
                        {...fieldProps}
                        onChange={(event) => setPrompt(event.target.value)}
                    />
                    <InputGroupAddon align="inline-end">
                        {actions}
                    </InputGroupAddon>
                </InputGroup>
            )}
            {error && (
                <FieldError id={errorId} className="px-1 text-xs">
                    {error}
                </FieldError>
            )}
        </form>
    );
}
