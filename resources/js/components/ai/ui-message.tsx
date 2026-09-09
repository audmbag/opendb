import type {
    ChatStatus,
    DataUIPart,
    FileUIPart,
    TextUIPart,
    UIMessage,
} from 'ai';
import type {DynamicToolUIPart} from 'ai';
import { CheckIcon, CopyIcon } from 'lucide-react';
import { memo, useCallback, useState } from 'react';
import ArtifactPreviewButtons from '@/components/ai/artifact-preview-button';
import AttachmentItem from '@/components/ai/attachment-item';
import { Attachments } from '@/components/ai-elements/attachments';
import {
    Message, MessageAction,
    MessageActions,
    MessageContent,
    MessageResponse,
} from '@/components/ai-elements/message';
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
};

const CopyAction = memo(({ content }: { content: string }) => {
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
            label="Copy"
            onClick={handleClick}
            tooltip="Copier le message"
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
}: Props) => {
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
                <MessageResponse>{(part as TextUIPart).text}</MessageResponse>
            </MessageContent>

            {artifactables && artifactables.length > 0 && (
                <ArtifactPreviewButtons artifactables={artifactables} />
            )}

            {(part.type === 'text' || part.type.startsWith('data-')) &&
                ((isLast && status === 'ready') || !isLast) && (
                    <MessageActions
                        className={cn({
                            'justify-end': message.role === 'user',
                        })}
                    >
                        <CopyAction content={(part as TextUIPart).text} />
                        <span className="text-sm text-muted-foreground">
                            {fromModel.map((info) => info.data).join('/')}
                        </span>
                    </MessageActions>
                )}
        </Message>
    );
}


export default UIMessage;
