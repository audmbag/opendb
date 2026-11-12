import { router, usePage } from '@inertiajs/react';
import type { DataUIPart, FileUIPart, ToolUIPart } from 'ai';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    EraserIcon,
    ImagePlusIcon,
    PencilRulerIcon,
    RefreshCcwIcon,
    XIcon,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { formatBytes } from '@/components/ai/artifacts/format';
import AttachmentItem from '@/components/ai/attachment-item';
import ModelSelector from '@/components/ai/model-selector';
import ProcessingIndicator from '@/components/ai/processing-indicator';
import Tool from '@/components/ai/tool';
import UIMessage from '@/components/ai/ui-message';
import { Attachments } from '@/components/ai-elements/attachments';
import {
    Conversation,
    ConversationContent,
    ConversationScrollButton,
} from '@/components/ai-elements/conversation';
import type { PromptInputMessage } from '@/components/ai-elements/prompt-input';
import {
    PromptInput,
    PromptInputBody,
    PromptInputButton,
    PromptInputFooter,
    PromptInputSubmit,
    PromptInputTextarea,
    PromptInputTools,
    usePromptInputAttachments,
} from '@/components/ai-elements/prompt-input';
import { Suggestion, Suggestions } from '@/components/ai-elements/suggestion';
import Alert from '@/components/alert';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { trans } from '@/lib/i18n';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';
import { useProviderStore } from '@/store/use-provider-store';
import type { SchemaApplyModeSetting } from '@/types/models/user';
import SettingsController from '@/wayfinder/actions/App/Http/Controllers/SettingsController';
import type { SchemaAssistant } from './use-schema-assistant';

const MAX_FILES = 3;
const MAX_FILE_SIZE = 10 * 1024 * 1024;

const MODES: SchemaApplyModeSetting[] = ['draft', 'additions', 'auto'];

const PICTURE_PROGRESS = [
    'reading_picture',
    'finding_tables',
    'designing',
    'preparing',
];

const REQUEST_PROGRESS = ['reading_request', 'designing', 'preparing'];

const SUGGESTIONS = [
    'erd.assistant.suggestions.invoices',
    'erd.assistant.suggestions.timestamps',
];

type Props = {
    assistant: SchemaAssistant;
    onClose: () => void;
};

/**
 * The message to show for a request the server refused before streaming, like a model that is no longer available.
 */
function requestErrorMessage(error: Error): string {
    try {
        const body = JSON.parse(error.message) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (body.errors?.provider || body.errors?.model) {
            return trans('erd.assistant.no_model');
        }

        return body.message ?? error.message;
    } catch {
        return trans('erd.assistant.failed');
    }
}

/**
 * The pictures attached to the message, before it is sent.
 */
function AttachedFiles() {
    const attachments = usePromptInputAttachments();

    if (!attachments.files.length) {
        return null;
    }

    return (
        <Attachments variant="grid" className="ml-0 w-full p-2">
            {attachments.files.map((attachment) => (
                <AttachmentItem
                    key={attachment.id}
                    attachment={attachment}
                    onRemove={(id) => attachments.remove(id)}
                />
            ))}
        </Attachments>
    );
}

function AttachButton({ disabled }: { disabled: boolean }) {
    const { t } = useLaravelReactI18n();
    const attachments = usePromptInputAttachments();

    return (
        <PromptInputButton
            onClick={() => attachments.openFileDialog()}
            disabled={disabled}
            aria-label={t('ai.prompt.add_file')}
            tooltip={t('ai.prompt.add_file')}
        >
            <ImagePlusIcon />
        </PromptInputButton>
    );
}

/**
 * Whether the chosen model is known not to read pictures. A model whose provider says nothing about it may.
 */
function useModelIgnoresPictures(): boolean {
    const selectedModel = useAgentSettingsStore((state) => state.selectedModel);
    const providers = useProviderStore((state) => state.providers);
    const modalities = providers
        .find((provider) => provider.id === selectedModel?.providerId)
        ?.models.find(
            (model) => model.id === selectedModel?.modelId,
        )?.modalities;

    return !!modalities?.length && !modalities.includes('image');
}

/**
 * The assistant of the diagram, next to it: the user describes tables or gives the picture of a diagram, and the
 * assistant changes the structure, drawn as a draft in the diagram or run at once as the user chose.
 */
export default function ErdAssistantPanel({ assistant, onClose }: Props) {
    const { t } = useLaravelReactI18n();
    const { auth } = usePage().props;
    const [input, setInput] = useState('');
    const [mode, setMode] = useState<SchemaApplyModeSetting>(
        auth.user.settings.agent.schemaApplyMode ?? 'draft',
    );
    const hasSelectedModel = useAgentSettingsStore(
        (state) => state.selectedModel !== null,
    );
    const ignoresPictures = useModelIgnoresPictures();
    const {
        messages,
        sendMessage,
        status,
        error,
        stop,
        setMessages,
        regenerate,
        streamError,
        clearStreamError,
        proposal,
    } = assistant;
    const busy = status === 'submitted' || status === 'streaming';
    const lastQuestion = messages.findLast(
        (message) => message.role === 'user',
    );
    const isReadingPicture =
        lastQuestion?.parts.some(
            (part) =>
                part.type === 'file' && part.mediaType.startsWith('image/'),
        ) ?? false;
    // A picture takes the model a long while: what it does is told step by step until its plan arrives.
    const awaitingLabels = (
        isReadingPicture ? PICTURE_PROGRESS : REQUEST_PROGRESS
    ).map((key) => t(`erd.assistant.progress.${key}`));
    const canSend = !busy && hasSelectedModel && !proposal;

    const ask = useCallback(
        (message: PromptInputMessage) => {
            const files = message.files.filter((file) =>
                file.mediaType.startsWith('image/'),
            );
            const text =
                message.text.trim() ||
                (files.length ? trans('erd.assistant.reproduce_image') : '');

            if (!text || !canSend) {
                return;
            }

            clearStreamError();
            setInput('');
            void sendMessage({ text, files });
        },
        [canSend, clearStreamError, sendMessage],
    );

    const changeMode = (next: SchemaApplyModeSetting) => {
        const previous = mode;

        setMode(next);
        requestTableData('put', SettingsController.updateAgent.url(), {
            data: { ...auth.user.settings.agent, schemaApplyMode: next },
        })
            .then(() => {
                toast.success(t('erd.assistant.mode.saved'));
                router.reload({ only: ['auth'] });
            })
            .catch((exception: TableRequestError) => {
                setMode(previous);
                toast.error(exception.first());
            });
    };

    return (
        <aside
            aria-label={t('erd.assistant.title')}
            className="flex min-h-0 flex-1 flex-col"
        >
            <div className="flex items-center gap-2 border-b px-3 py-2">
                <PencilRulerIcon className="size-4 shrink-0 text-muted-foreground" />
                <h2 className="min-w-0 flex-1 truncate text-sm font-medium">
                    {t('erd.assistant.title')}
                </h2>
                {messages.length > 0 && (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        disabled={busy}
                        aria-label={t('erd.assistant.reset')}
                        tooltip={t('erd.assistant.reset')}
                        onClick={() => {
                            setMessages([]);
                            clearStreamError();
                        }}
                    >
                        <EraserIcon />
                    </Button>
                )}
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('erd.assistant.close')}
                    tooltip={t('erd.assistant.close')}
                    onClick={onClose}
                >
                    <XIcon />
                </Button>
            </div>

            <div className="flex flex-col gap-1.5 border-b px-3 py-2">
                <span
                    id="erd-assistant-mode"
                    className="text-xs text-muted-foreground"
                >
                    {t('erd.assistant.mode.label')}
                </span>
                <Select
                    value={mode}
                    onValueChange={(value) =>
                        changeMode(value as SchemaApplyModeSetting)
                    }
                >
                    <SelectTrigger
                        size="sm"
                        className="w-full"
                        aria-labelledby="erd-assistant-mode"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {MODES.map((item) => (
                            <SelectItem key={item} value={item}>
                                <span className="flex flex-col items-start">
                                    <span>
                                        {t(`erd.assistant.mode.${item}`)}
                                    </span>
                                    <span className="text-xs text-muted-foreground in-data-[slot=select-value]:hidden">
                                        {t(
                                            `erd.assistant.mode.${item}_description`,
                                        )}
                                    </span>
                                </span>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {messages.length === 0 ? (
                <div className="flex min-h-0 flex-1 flex-col justify-center gap-4 overflow-y-auto p-4">
                    <EmptyState
                        icon={PencilRulerIcon}
                        title={t('erd.assistant.empty_title')}
                        description={t('erd.assistant.empty_description')}
                        className="flex-none p-0"
                    />
                    <Suggestions className="w-full flex-col items-stretch">
                        {SUGGESTIONS.map((suggestion) => (
                            <Suggestion
                                key={suggestion}
                                suggestion={t(suggestion)}
                                onClick={(text) => ask({ text, files: [] })}
                                disabled={!canSend}
                                className="justify-start rounded-lg"
                            />
                        ))}
                    </Suggestions>
                </div>
            ) : (
                <Conversation
                    resize="instant"
                    initial="instant"
                    className="relative min-h-0 flex-1"
                >
                    <ConversationContent className="gap-4 p-4">
                        {messages.map((message, messageIndex) => {
                            const isLast = messageIndex === messages.length - 1;
                            const attachments = message.parts.filter(
                                (part): part is FileUIPart =>
                                    part.type === 'file',
                            );
                            const fromModel = message.parts.filter((part) =>
                                part.type.startsWith('data-'),
                            ) as DataUIPart<{ [key: string]: string }>[];

                            return message.parts.map((part, partIndex) => {
                                if (part.type === 'text') {
                                    return (
                                        <UIMessage
                                            key={`${message.id}-${partIndex}`}
                                            message={message}
                                            messageIndex={1}
                                            part={part}
                                            attachments={attachments}
                                            status={status}
                                            isLast={isLast}
                                            partIndex={partIndex}
                                            fromModel={fromModel}
                                        />
                                    );
                                }

                                if (part.type.startsWith('tool-')) {
                                    return (
                                        <Tool
                                            key={
                                                (part as ToolUIPart).toolCallId
                                            }
                                            part={part as ToolUIPart}
                                        />
                                    );
                                }

                                return null;
                            });
                        })}

                        <ProcessingIndicator
                            status={status}
                            latestMessage={messages[messages.length - 1]}
                            awaitingLabels={awaitingLabels}
                        />

                        {error && (
                            <Alert
                                title={t('common.error')}
                                mode="frame"
                                variant="destructive"
                                message={
                                    streamError ?? requestErrorMessage(error)
                                }
                                buttons={[
                                    {
                                        label: t('common.retry'),
                                        icon: RefreshCcwIcon,
                                        onClick: () => void regenerate(),
                                    },
                                ]}
                            />
                        )}
                    </ConversationContent>
                    <ConversationScrollButton />
                </Conversation>
            )}

            <div className="flex flex-col gap-2 border-t p-3">
                {ignoresPictures && (
                    <p className="text-xs text-muted-foreground">
                        {t('erd.assistant.vision_warning')}
                    </p>
                )}
                <PromptInput
                    accept="image/*"
                    multiple
                    globalDrop
                    maxFiles={MAX_FILES}
                    maxFileSize={MAX_FILE_SIZE}
                    onError={(failure) => {
                        if (failure.code === 'max_files') {
                            toast.error(
                                t('ai.prompt.max_files', {
                                    count: String(MAX_FILES),
                                }),
                            );
                        }

                        if (failure.code === 'max_file_size') {
                            toast.error(
                                t('ai.prompt.max_file_size', {
                                    size: formatBytes(MAX_FILE_SIZE),
                                }),
                            );
                        }
                    }}
                    onSubmit={ask}
                >
                    <AttachedFiles />
                    <PromptInputBody>
                        <PromptInputTextarea
                            value={input}
                            onChange={(event) => setInput(event.target.value)}
                            placeholder={t('erd.assistant.placeholder')}
                            className="min-h-16"
                            aria-label={t('erd.assistant.placeholder')}
                        />
                    </PromptInputBody>
                    <PromptInputFooter className="p-2 pt-0">
                        <PromptInputTools>
                            <AttachButton disabled={busy} />
                            <ModelSelector size="xs" />
                        </PromptInputTools>
                        <PromptInputSubmit
                            aria-label={
                                busy ? t('ai.prompt.stop') : t('ai.prompt.send')
                            }
                            status={status}
                            onStop={stop}
                            disabled={!busy && !canSend}
                        />
                    </PromptInputFooter>
                </PromptInput>
            </div>
        </aside>
    );
}
