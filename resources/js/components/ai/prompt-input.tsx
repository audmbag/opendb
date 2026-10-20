import { usePage } from '@inertiajs/react';
import {
    ArrowUpIcon,
    BrainCircuitIcon,
    BrainIcon,
    PaperclipIcon,
    PencilIcon,
    PencilOffIcon,
} from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
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
import { Kbd } from '@/components/ui/kbd';
import {
    Mention,
    MentionContent,
    MentionInput,
    MentionItem,
} from '@/components/ui/mention';
import { getFileIcon } from '@/hooks/use-file-tree';
import { getMentionQuery, searchProjectFiles, useProjectFiles } from '@/hooks/use-project-files';
import { cn } from '@/lib/utils';
import type { Project } from '@/types/models/project';




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

interface FileMentionProps extends React.ComponentProps<typeof Mention> {
    query: string;
    paths: string[] | null;
    isLoading: boolean;
}

const FileMention = ({ children, query, paths, isLoading, ...props }: FileMentionProps) => {
    const entries = useMemo(
        () => searchProjectFiles(paths ?? [], query),
        [paths, query],
    );

    return (
        <Mention onFilter={(options) => options} {...props}>
            <MentionInput className="border-none shadow-none focus-visible:ring-0" asChild>
                {children}
            </MentionInput>
            <MentionContent className="flex max-h-80 w-96 max-w-[calc(100vw-2rem)] flex-col p-1">
                <div className="flex items-center justify-between gap-2 px-2 pt-1 pb-1.5 text-xs text-muted-foreground">
                    <span>Fichiers du projet</span>
                    <span className="flex items-center gap-1">
                        <Kbd>↑</Kbd>
                        <Kbd>↓</Kbd>
                        <Kbd>↵</Kbd>
                    </span>
                </div>
                <div className="min-h-0 overflow-y-auto">
                    {entries.length === 0 ? (
                        <MentionItem value="__empty" disabled className="text-muted-foreground">
                            {isLoading ? 'Chargement des fichiers...' : 'Aucun fichier correspondant'}
                        </MentionItem>
                    ) : (
                        entries.map((entry) => (
                            <MentionItem key={entry.path} value={entry.path} title={entry.path}>
                                {getFileIcon(entry, false)}
                                <span className="shrink-0">{entry.name}</span>
                                {entry.directory && !query.endsWith('/') && (
                                    <span className="min-w-0 truncate text-xs text-muted-foreground [direction:rtl]">
                                        <bdi>{entry.directory}</bdi>
                                    </span>
                                )}
                            </MentionItem>
                        ))
                    )}
                </div>
            </MentionContent>
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
    const { props: { project } } = usePage<{ project?: Project }>();

    const [mentions, setMentions] = useState<string[]>([]);
    const [mentionQuery, setMentionQuery] = useState('');
    const [mentionKey, setMentionKey] = useState(0);
    const [prompt, setPrompt] = useState('');
    const [isSubmitted, setIsSubmitted] = useState(false);

    const { paths, load: loadProjectFiles, isLoading: isLoadingProjectFiles } = useProjectFiles(project?.id);

    useEffect(() => {
        loadProjectFiles();
    }, [loadProjectFiles]);

    const handleMentionQueryChange = useCallback(
        (event: React.SyntheticEvent<HTMLTextAreaElement>) => {
            const { value, selectionStart } = event.currentTarget;
            const query = getMentionQuery(value, selectionStart);

            if (query !== null) {
                loadProjectFiles();
            }

            setMentionQuery(query ?? '');
        },
        [loadProjectFiles],
    );

    const handleSubmitPromptMessage = useCallback(
        async (message: AIPromptInputMessage) => {
            setPrompt('');
            setMentions([]);
            setMentionKey((key) => key + 1);
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
                        '*:data-[slot=input-group]:rounded-t-xl *:data-[slot=input-group]:rounded-b-xl':
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
                    <FileMention
                        key={mentionKey}
                        className="w-full"
                        value={mentions}
                        onValueChange={setMentions}
                        inputValue={prompt}
                        onInputValueChange={setPrompt}
                        query={mentionQuery}
                        paths={paths}
                        isLoading={isLoadingProjectFiles}
                    >
                        <PromptInputTextarea
                            className="min-h-15 py-4"
                            placeholder="Démander n'importe quoi... (@ pour mentionner un fichier)"
                            value={prompt}
                            onChange={(e) => {
                                setPrompt(e.target.value);
                                handleMentionQueryChange(e);
                            }}
                            onSelect={handleMentionQueryChange}
                            onFocus={loadProjectFiles}
                            disabled={loading}
                        />
                    </FileMention>
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
                    optionsLabel="Mode d'accès"
                    defaultValue="read-only"
                    position="item-aligned"
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
                        optionsLabel="Effort de réflexion"
                        defaultValue="default"
                        position="item-aligned"
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
