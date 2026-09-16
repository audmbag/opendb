import {
    ArrowUpIcon,
    BrainCircuitIcon,
    BrainIcon,
    PaperclipIcon,
    PencilIcon,
    PencilOffIcon,
} from 'lucide-react';
import React, { useCallback, useState } from 'react';
import { toast } from 'sonner';
import AttachmentItem from '@/components/ai/attachment-item';
import ModelSelector from '@/components/ai/model-selector';
import { Attachments } from '@/components/ai-elements/attachments';
import type { PromptInputMessage as AIPromptInputMessage} from '@/components/ai-elements/prompt-input';
import {
    usePromptInputAttachments,
} from '@/components/ai-elements/prompt-input';
import {
    PromptInputFooter,
    PromptInputSubmit,
    PromptInputTools,
} from '@/components/ai-elements/prompt-input';
import {
    PromptInputBody,
    PromptInputTextarea,
} from '@/components/ai-elements/prompt-input';
import { PromptInput as PromptInputComponent } from '@/components/ai-elements/prompt-input';
import Button from '@/components/form/button';
import Select from '@/components/form/select';
import Presence from '@/components/presence';
import PromptSuggestions from '@/components/prompt-suggestions';
import {
    Mention,
    MentionContent,
    MentionInput,
    MentionItem,
} from '@/components/ui/mention';
import { cn } from '@/lib/utils';




const PromptInputAttachmentsDisplay = () => {
    const attachments = usePromptInputAttachments();

    const handleRemove = useCallback(
        (id: string) => attachments.remove(id),
        [attachments],
    );

    if (attachments.files.length === 0) {
        return null;
    }

    return (
        <Attachments variant="grid" className="ml-0 w-full p-2">
            {attachments.files.map((attachment) => (
                <AttachmentItem
                    attachment={attachment}
                    key={attachment.id}
                    onRemove={handleRemove}
                />
            ))}
        </Attachments>
    );
};

const AttachmentButton = (props: React.ComponentProps<typeof Button>) => {

    const attachments = usePromptInputAttachments();

    const handleSelectAttachments = useCallback(() => {
        attachments.openFileDialog();
    }, [attachments]);

    return (
        <Button
            {...props}
            tooltip={['Ajouter un fichier', ['Ctrl', 'U']]}
            icon={PaperclipIcon}
            variant="secondary"
            className="size-10 rounded-full"
            onClick={handleSelectAttachments}
            size="sm"
        />
    );
}

const users: any[] = [
    // {
    //     id: "1",
    //     name: "OliviaMartin",
    //     email: "olivia@email.com",
    // },
    // {
    //     id: "2",
    //     name: "IsabellaNguyen",
    //     email: "isabella@email.com",
    // },
    // {
    //     id: "3",
    //     name: "EmmaWilson",
    //     email: "emma@email.com",
    // },
    // {
    //     id: "4",
    //     name: "JacksonLee",
    //     email: "jackson@email.com",
    // },
    // {
    //     id: "5",
    //     name: "WilliamKim",
    //     email: "will@email.com",
    // },
];

const MentionWrapper = ({ children, ...props }: React.ComponentProps<typeof Mention>) => {
    return (
        <Mention {...props}>
            <MentionInput className="shadow-none border-none focus-visible:ring-0" asChild>{children}</MentionInput>
            {users.length > 0 && <MentionContent className="relative z-50 min-w-(--dice-anchor-width) overflow-hidden rounded-md border border-zinc-200 bg-white p-1 text-zinc-950 shadow-md data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50">
                {users.map((user) => (
                    <MentionItem
                        key={user.id}
                        value={user.name}
                        className="relative flex w-full cursor-default flex-col rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-highlighted:bg-zinc-100 data-highlighted:text-zinc-900 dark:data-highlighted:bg-zinc-800 dark:data-highlighted:text-zinc-50 data-disabled:pointer-events-none data-disabled:opacity-50"
                    >
                        <span className="text-sm">{user.name}</span>
                        <span className="text-xs text-muted-foreground">
                            {user.email}
                        </span>
                    </MentionItem>
                ))}
            </MentionContent>}
        </Mention>
    );
};


export default function PromptInput({
    className,
    onSubmit,
    suggestions = true,
    loading = false,
    variant = 'primary',
    ...props
}: {
    suggestions?: boolean;
    onSubmit?: (message: AIPromptInputMessage) => void;
    loading?: boolean;
    variant?: 'primary' | 'secondary';
} & Omit<React.ComponentProps<'div'>, 'children' | 'onSubmit'>) {
    const [mentions, setMentions] = useState<string[]>([]);
    const [prompt, setPrompt] = useState('');
    const [isSubmitted, setIsSubmitted] = useState(false);

    const handleSubmitPromptMessage = useCallback(
        async (message: AIPromptInputMessage) => {
            setPrompt('');
            setIsSubmitted(true);

            onSubmit?.(message);
        },
        [onSubmit],
    );

    return (
        <div
            className={cn('flex w-full max-w-4xl flex-col', className)}
            {...props}
        >
            <PromptInputComponent
                onSubmit={handleSubmitPromptMessage}
                className={cn(
                    'relative z-20 *:data-[slot=input-group]:rounded-b-4xl *:data-[slot=input-group]:bg-background *:data-[slot=input-group]:p-0 dark:*:data-[slot=input-group]:bg-background',
                    {
                        '*:data-[slot=input-group]:rounded-t-2xl':
                            variant === 'primary',
                        '*:data-[slot=input-group]:rounded-t-4xl':
                            variant === 'secondary',
                    },
                )}
                globalDrop
                multiple
                maxFiles={3}
                maxFileSize={10 * 1024 * 1024}
                onError={(error) => {
                    if (error.code === 'max_files') {
                        toast.error(
                            'Vous ne pouvez pas ajouter plus de 3 fichiers à la fois.',
                        );
                    }

                    if (error.code === 'max_file_size') {
                        toast.error(
                            'Le fichier sélectionné dépasse la taille maximale autorisée de 10MB.',
                        );
                    }
                }}
            >
                <PromptInputAttachmentsDisplay />
                <PromptInputBody>
                    <MentionWrapper
                        className="w-full"
                        value={mentions}
                        onValueChange={setMentions}
                    >
                        <PromptInputTextarea
                            className="min-h-15 py-4"
                            placeholder="Démander n'importe quoi..."
                            value={prompt}
                            onChange={(e) => setPrompt(e.target.value)}
                            disabled={loading}
                        />
                    </MentionWrapper>
                </PromptInputBody>
                <PromptInputFooter className="p-2 pt-0">
                    <PromptInputTools>
                        <AttachmentButton disabled={loading} />
                    </PromptInputTools>
                    <PromptInputSubmit asChild>
                        <Button
                            tooltip={['Envoyer le prompt', ['⏎']]}
                            icon={ArrowUpIcon}
                            className="size-10 rounded-full"
                            disabled={!prompt || loading}
                            processing={loading}
                            size="sm"
                        />
                    </PromptInputSubmit>
                </PromptInputFooter>
            </PromptInputComponent>
            <div
                className={cn(
                    '-mt-6 flex items-center justify-between gap-2 pt-8 pb-2',
                    {
                        'rounded-b-lg border border-t-0 border-border bg-sidebar px-2':
                            variant === 'primary',
                    },
                )}
            >
                <Select
                    label="Mode d'accès"
                    defaultValue="read-only"
                    align="start"
                    button
                    tooltip={["Choisir le mode d'action", ['Ctrl', '.']]}
                    items={[
                        {
                            label: 'Lecture seule',
                            value: 'read-only',
                            icon: PencilOffIcon,
                        },
                        {
                            label: 'Lecture et écriture',
                            value: 'read-write',
                            icon: PencilIcon,
                        },
                    ]}
                />
                <div className="flex items-center gap-2">
                    <ModelSelector />
                    <Select
                        label="Effort de réflexion"
                        defaultValue="default"
                        align="end"
                        button
                        tooltip={[
                            "Choisir l'effort de réflexion",
                            ['Ctrl', 'Shift', 'D'],
                        ]}
                        items={[
                            {
                                label: 'Par défaut',
                                value: 'default',
                                icon: BrainIcon,
                            },
                            {
                                label: 'Élevé',
                                value: 'read-write',
                                icon: BrainCircuitIcon,
                            },
                        ]}
                    />
                </div>
            </div>
            {suggestions && !isSubmitted && (
                <Presence present={!prompt}>
                    <PromptSuggestions onClick={(value) => setPrompt(value)} />
                </Presence>
            )}
        </div>
    );
}
