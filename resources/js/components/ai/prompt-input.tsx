import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BrainCircuitIcon,
    BrainIcon,
    CornerDownLeftIcon,
    FolderTreeIcon,
    PaperclipIcon,
    PencilIcon,
    PencilOffIcon,
    PlusIcon,
    SquareIcon,
} from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useHotkeys } from 'react-hotkeys-hook';
import { toast } from 'sonner';
import { formatBytes } from '@/components/ai/artifacts/format';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { InputGroupAddon } from '@/components/ui/input-group';
import { Kbd } from '@/components/ui/kbd';
import {
    Mention,
    MentionContent,
    MentionInput,
    MentionItem,
} from '@/components/ui/mention';
import { getFileIcon } from '@/hooks/use-file-tree';
import { usePrivileges } from '@/hooks/use-privileges';
import { getMentionQuery, searchProjectFiles, useProjectFiles } from '@/hooks/use-project-files';
import { PROMPT_HOTKEY_OPTIONS } from '@/lib/hotkeys';
import { cn } from '@/lib/utils';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';
import type { AgentAccessMode, AgentEffort } from '@/store/use-agent-settings-store';
import type { Project } from '@/types/models/project';




const PromptInputAttachmentsDisplay = ({ className }: { className?: string }) => {
    const attachments = usePromptInputAttachments();

    const handleRemove = useCallback(
        (id: string) => attachments.remove(id),
        [attachments],
    );

    if (attachments.files.length === 0) {
        return null;
    }

    return (
        <Attachments variant="grid" className={cn('ml-0 w-full p-2', className)}>
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

interface AttachmentButtonProps extends React.ComponentProps<typeof Button> {
    /**
     * Start a project file mention in the prompt, once the menu has given the focus back.
     */
    onMentionProjectFile: () => void;
}

const AttachmentButton = ({ className, size = 'sm', onMentionProjectFile, ...props }: AttachmentButtonProps) => {
    const { t } = useLaravelReactI18n();

    const attachments = usePromptInputAttachments();
    const mentionAfterClose = useRef(false);

    const handleSelectAttachments = useCallback(() => {
        attachments.openFileDialog();
    }, [attachments]);

    useHotkeys('mod+u', handleSelectAttachments, { ...PROMPT_HOTKEY_OPTIONS, enabled: !props.disabled });

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    {...props}
                    tooltip={[t('ai.prompt.add_file'), ['Ctrl', 'U']]}
                    aria-label={t('ai.prompt.add_file')}
                    icon={PlusIcon}
                    variant="secondary"
                    className={cn('size-10 rounded-full', className)}
                    size={size}
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-48"
                onCloseAutoFocus={(event) => {
                    // The mention list opens on the prompt: the focus must go there rather than back to the button.
                    if (mentionAfterClose.current) {
                        event.preventDefault();
                        mentionAfterClose.current = false;
                        onMentionProjectFile();
                    }
                }}
            >
                <DropdownMenuItem onSelect={handleSelectAttachments}>
                    <PaperclipIcon />
                    {t('ai.prompt.add_file')}
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => (mentionAfterClose.current = true)}>
                    <FolderTreeIcon />
                    {t('ai.prompt.project_files')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
};

interface FileMentionProps extends React.ComponentProps<typeof Mention> {
    query: string;
    paths: string[] | null;
    isLoading: boolean;
}

const FileMention = ({ children, query, paths, isLoading, ...props }: FileMentionProps) => {
    const { t } = useLaravelReactI18n();
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
                    <span>{t('ai.prompt.project_files')}</span>
                    <span className="flex items-center gap-1">
                        <Kbd>↑</Kbd>
                        <Kbd>↓</Kbd>
                        <Kbd>↵</Kbd>
                    </span>
                </div>
                <div className="min-h-0 overflow-y-auto">
                    {entries.length === 0 ? (
                        <MentionItem value="__empty" disabled className="text-muted-foreground">
                            {isLoading ? t('ai.prompt.loading_files') : t('ai.prompt.no_matching_file')}
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
    onStop,
    variant = 'primary',
    ...props
}: {
    suggestions?: boolean;
    onSubmit?: (message: AIPromptInputMessage) => void;
    loading?: boolean;
    /**
     * Stop the answer being written: while loading, the submit button becomes a stop button.
     */
    onStop?: () => void;
    variant?: 'primary' | 'secondary';
} & Omit<React.ComponentProps<'div'>, 'children' | 'onSubmit'>) {
    const { t } = useLaravelReactI18n();
    const { props: { project } } = usePage<{ project?: Project }>();
    const isCompact = variant === 'secondary';

    // The agent never does more than the user: without a privilege changing the database, it only reads.
    const { writes } = usePrivileges();
    const chosenAccessMode = useAgentSettingsStore((state) => state.accessMode);
    const accessMode: AgentAccessMode = writes ? chosenAccessMode : 'read-only';
    const setAccessMode = useAgentSettingsStore((state) => state.setAccessMode);
    const effort = useAgentSettingsStore((state) => state.effort);
    const setEffort = useAgentSettingsStore((state) => state.setEffort);
    const hasSelectedModel = useAgentSettingsStore((state) => state.selectedModel !== null);

    // The dot needs Shift on an AZERTY keyboard.
    useHotkeys(
        ['mod+.', 'mod+shift+.'],
        () => writes && setAccessMode(accessMode === 'read-only' ? 'read-write' : 'read-only'),
        PROMPT_HOTKEY_OPTIONS,
        [accessMode, setAccessMode, writes],
    );

    useHotkeys(
        'mod+shift+d',
        () => setEffort(effort === 'default' ? 'high' : 'default'),
        PROMPT_HOTKEY_OPTIONS,
        [effort, setEffort],
    );

    const [mentions, setMentions] = useState<string[]>([]);
    const [mentionQuery, setMentionQuery] = useState('');
    const [mentionKey, setMentionKey] = useState(0);
    const [prompt, setPrompt] = useState('');
    const [isSubmitted, setIsSubmitted] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const [compactAttachmentSlot, setCompactAttachmentSlot] = useState<HTMLDivElement | null>(null);
    const toolbarControlSize = isCompact ? 'xs' : undefined;

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

    /**
     * Fill the prompt from code rather than typing. The mention highlighter, which displays the text over the
     * transparent textarea, renders it from the textarea's DOM value: writing the DOM before the state keeps it from
     * showing the previous value.
     */
    const fillPrompt = useCallback((value: string) => {
        const textarea = textareaRef.current;

        if (textarea) {
            textarea.value = value;
            textarea.setSelectionRange(value.length, value.length);
        }

        setPrompt(value);
        setMentions([]);
    }, []);

    /**
     * Type the mention trigger at the caret, as the user would: the input event lets the mention list open and shift
     * the mentions already placed. The trigger stays apart from the surrounding words, otherwise it opens nothing.
     */
    const mentionProjectFile = useCallback(() => {
        const textarea = textareaRef.current;

        if (!textarea) {
            return;
        }

        textarea.focus();

        const { selectionStart, selectionEnd, value } = textarea;
        const textBefore = value.slice(0, selectionStart);
        const textAfter = value.slice(selectionEnd);
        const spaceBefore = textBefore === '' || /\s$/.test(textBefore) ? '' : ' ';
        const spaceAfter = textAfter === '' || /^\s/.test(textAfter) ? '' : ' ';
        const caret = selectionStart + spaceBefore.length + 1;

        textarea.setRangeText(`${spaceBefore}@${spaceAfter}`, selectionStart, selectionEnd);
        textarea.setSelectionRange(caret, caret);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }, []);

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

    const submitButton = loading && onStop ? (
        <Button
            tooltip={t('ai.prompt.stop')}
            aria-label={t('ai.prompt.stop')}
            icon={SquareIcon}
            className={cn('size-10 rounded-full [&_svg]:fill-current', {
                'size-8 rounded-lg': isCompact,
            })}
            onClick={onStop}
            size="sm"
        />
    ) : (
        <PromptInputSubmit asChild>
            <Button
                tooltip={
                    hasSelectedModel
                        ? [t('ai.prompt.send'), ['⏎']]
                        : t('ai.prompt.select_model')
                }
                icon={CornerDownLeftIcon}
                className={cn('size-10 rounded-full', {
                    'size-8 rounded-lg': isCompact,
                })}
                disabled={!prompt || loading || !hasSelectedModel}
                processing={loading}
                size="sm"
            />
        </PromptInputSubmit>
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
                        '*:data-[slot=input-group]:h-auto *:data-[slot=input-group]:flex-wrap *:data-[slot=input-group]:rounded-t-xl *:data-[slot=input-group]:rounded-b-xl':
                            isCompact,
                    },
                )}
                globalDrop
                multiple
                maxFiles={3}
                maxFileSize={10 * 1024 * 1024}
                onError={(error) => {
                    if (error.code === 'max_files') {
                        toast.error(t('ai.prompt.max_files', { count: 3 }));
                    }

                    if (error.code === 'max_file_size') {
                        toast.error(t('ai.prompt.max_file_size', { size: formatBytes(10 * 1024 * 1024) }));
                    }
                }}
            >
                <PromptInputAttachmentsDisplay
                    className={cn({ 'order-first': isCompact })}
                />
                <PromptInputBody>
                    <FileMention
                        key={mentionKey}
                        className={
                            isCompact ? 'min-w-0 grow basis-1/2' : 'w-full'
                        }
                        value={mentions}
                        onValueChange={setMentions}
                        inputValue={prompt}
                        onInputValueChange={setPrompt}
                        query={mentionQuery}
                        paths={paths}
                        isLoading={isLoadingProjectFiles}
                    >
                        <PromptInputTextarea
                            ref={textareaRef}
                            className={
                                isCompact ? 'min-h-12 py-3.5' : 'min-h-15 py-4'
                            }
                            placeholder={t('ai.prompt.placeholder')}
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
                {isCompact ? (
                    <>
                        <InputGroupAddon
                            align="inline-end"
                            className="self-end py-2 pr-2 has-[>button]:mr-0"
                        >
                            {submitButton}
                        </InputGroupAddon>
                        {/* Rendered in the toolbar, outside the form, while keeping the attachments context of the prompt input. */}
                        {compactAttachmentSlot &&
                            createPortal(
                                <AttachmentButton
                                    disabled={loading}
                                    onMentionProjectFile={mentionProjectFile}
                                    size="icon-xs"
                                    className="size-6 rounded-sm"
                                />,
                                compactAttachmentSlot,
                            )}
                    </>
                ) : (
                    <PromptInputFooter className="p-2 pt-0">
                        <PromptInputTools>
                            <AttachmentButton disabled={loading} onMentionProjectFile={mentionProjectFile} />
                        </PromptInputTools>
                        {submitButton}
                    </PromptInputFooter>
                )}
            </PromptInputComponent>
            <div
                className={cn('flex items-center justify-between gap-2 pb-2', {
                    '-mt-6 rounded-b-lg border border-t-0 border-border bg-sidebar px-2 pt-8':
                        !isCompact,
                    'pt-2': isCompact,
                })}
            >
                <div className="flex items-center gap-1">
                    {isCompact && (
                        <div
                            ref={setCompactAttachmentSlot}
                            className="contents"
                        />
                    )}
                    <Select
                        optionsLabel={t('ai.prompt.access_mode')}
                        value={accessMode}
                        onValueChange={(value) =>
                            setAccessMode(value as AgentAccessMode)
                        }
                        position={isCompact ? 'popper' : 'item-aligned'}
                        button
                        size={toolbarControlSize}
                        tooltip={[t('shortcuts.access_mode'), ['Ctrl', '.']]}
                        items={[
                            {
                                label: t('ai.access_modes.read-only'),
                                value: 'read-only',
                                icon: PencilOffIcon,
                            },
                            ...(writes
                                ? [
                                      {
                                          label: t('ai.access_modes.read-write'),
                                          value: 'read-write',
                                          icon: PencilIcon,
                                      },
                                  ]
                                : []),
                        ]}
                    />
                </div>
                <div className="flex items-center gap-2">
                    <ModelSelector size={toolbarControlSize} />
                    <Select
                        optionsLabel={t('ai.prompt.effort')}
                        value={effort}
                        onValueChange={(value) =>
                            setEffort(value as AgentEffort)
                        }
                        position={isCompact ? 'popper' : 'item-aligned'}
                        button
                        size={toolbarControlSize}
                        tooltip={[
                            t('shortcuts.effort'),
                            ['Ctrl', 'Shift', 'D'],
                        ]}
                        items={[
                            {
                                label: t('ai.efforts.default'),
                                value: 'default',
                                icon: BrainIcon,
                            },
                            {
                                label: t('ai.efforts.high'),
                                value: 'high',
                                icon: BrainCircuitIcon,
                            },
                        ]}
                    />
                </div>
            </div>
            {suggestions && !isSubmitted && (
                <Presence present={!prompt}>
                    <PromptSuggestions onClick={(value) => fillPrompt(value)} />
                </Presence>
            )}
        </div>
    );
}
