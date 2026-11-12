import type {
    ChatStatus,
    DataUIPart,
    FileUIPart,
    TextUIPart,
    UIMessage,
} from 'ai';
import type {DynamicToolUIPart} from 'ai';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CheckIcon, CircleStopIcon, CopyIcon, RefreshCcwIcon } from 'lucide-react';
import { memo, useCallback, useState } from 'react';
import ArtifactPreviewButton from '@/components/ai/artifact-preview-button';
import AttachmentItem from '@/components/ai/attachment-item';
import { markdownTableComponents } from '@/components/ai/markdown-table';
import { Attachments } from '@/components/ai-elements/attachments';
import {
    Message, MessageAction,
    MessageActions,
    MessageContent,
    MessageResponse,
} from '@/components/ai-elements/message';
import { useSyntaxTheme } from '@/hooks/use-syntax-theme';
import { uppercaseSqlBlocks } from '@/lib/sql';
import { cn } from '@/lib/utils';
import type { CustomOutputToolUIPart } from '@/types/agent';

type Props = {
    message: UIMessage;
    messageIndex: number;
    attachments: FileUIPart[];
    part: TextUIPart | DataUIPart<{ [key: string]: string }>;
    status: ChatStatus;
    isLast?: boolean;
    partIndex: number;
    fromModel: DataUIPart<{ [key: string]: string }>[];
    onToolApprovalHandle?: (value: {
        id: string;
        approved: boolean;
        result?: string;
    }) => void;
    approvalRequestPart?: DynamicToolUIPart;
    artifactables?: CustomOutputToolUIPart[];
    /**
     * Whether the user stopped this answer before its end.
     */
    isCancelled?: boolean;
    /**
     * Answer the last message of the user again, given on the last messages of the conversation only.
     */
    onRetry?: () => void;
    /**
     * Whether the actions follow this text, false for a text the answer goes on after, so they show once at its end.
     */
    hasActions?: boolean;
    /**
     * The text the copy action copies, the whole answer when it is written in several parts.
     */
    copyText?: string;
};

const CopyAction = memo(({ content }: { content: string }) => {
    const { t } = useLaravelReactI18n();
    const [copied, setCopied] = useState(false);

    const handleClick = useCallback(async () => {
        if (copied) {
            return;
        }

        setCopied(true);
        await navigator.clipboard.writeText(content);
        setTimeout(() => setCopied(false), 2000);
    }, [content, copied]);

    return (
        <MessageAction
            label={t('common.copy')}
            onClick={handleClick}
            tooltip={t('ai.messages.copy')}
        >
            {copied ? (
                <CheckIcon className="size-4 text-muted-foreground" />
            ) : (
                <CopyIcon className="size-4 text-muted-foreground" />
            )}
        </MessageAction>
    );
});

const UIMessage = ({
    message,
    part,
    attachments,
    status,
    isLast,
    partIndex,
    fromModel,
    artifactables,
    messageIndex,
    isCancelled = false,
    onRetry,
    hasActions = true,
    copyText,
}: Props) => {
    const { t } = useLaravelReactI18n();
    const { definition: syntaxTheme } = useSyntaxTheme();

    return (
        <Message from={message.role} className={cn('group', {'mt-8': messageIndex === 0})}>
            {attachments.length > 0 && (
                <Attachments variant="grid">
                    {attachments.map((attachment, index) => (
                        <AttachmentItem
                            key={
                                attachment.url ??
                                `${message.id}-file-${partIndex}`
                            }
                            attachment={{
                                ...attachment,
                                id:
                                    attachment.filename ??
                                    `${message.id}-file-${index}`,
                            }}
                        />
                    ))}
                </Attachments>
            )}
            <MessageContent>
                {/* MessageResponse redraws only for a new text: the key redraws it for a new syntax theme. */}
                <MessageResponse
                    key={syntaxTheme.value}
                    shikiTheme={[syntaxTheme.light, syntaxTheme.dark]}
                    components={markdownTableComponents}
                >
                    {uppercaseSqlBlocks((part as TextUIPart).text)}
                </MessageResponse>
            </MessageContent>

            {artifactables && artifactables.length > 0 && (
                <ArtifactPreviewButton
                    artifactables={artifactables}
                    isMessageSaved={!isLast || status === 'ready'}
                />
            )}

            {hasActions && (part.type === 'text' || part.type.startsWith('data-')) &&
                ((isLast && status === 'ready') || !isLast) && (
                    <MessageActions
                        className={cn({
                            'justify-end': message.role === 'user',
                        })}
                    >
                        <CopyAction content={copyText ?? (part as TextUIPart).text} />
                        {onRetry && (
                            <MessageAction
                                label={t('common.retry')}
                                onClick={onRetry}
                                tooltip={message.role === 'user' ? t('ai.messages.resend') : t('ai.messages.regenerate')}
                            >
                                <RefreshCcwIcon className="size-4 text-muted-foreground" />
                            </MessageAction>
                        )}
                        <span className="text-sm text-muted-foreground">
                            {fromModel.map((info) => info.data).join('/')}
                        </span>
                        {isCancelled && (
                            <span className="flex items-center gap-1 text-sm text-muted-foreground">
                                <CircleStopIcon className="size-3.5" />
                                {t('ai.messages.interrupted')}
                            </span>
                        )}
                    </MessageActions>
                )}
        </Message>
    );
}


export default UIMessage;
