import {
    FileArchiveIcon,
    FileCodeIcon,
    FileIcon,
    FileSpreadsheetIcon,
    FileTextIcon,
    ImageIcon,
} from 'lucide-react';
import React, { memo, useCallback } from 'react';
import type { AttachmentData} from '@/components/ai-elements/attachments';
import {
    Attachment,
    AttachmentPreview,
    AttachmentRemove,
} from '@/components/ai-elements/attachments';
import Tooltip from '@/components/tooltip';


interface AttachmentItemProps {
    attachment: AttachmentData;
    onRemove?: (id: string) => void;
}

const mediaTypeIcons: Record<string, React.ReactNode> = {
    'application/pdf': <FileTextIcon className="size-6" />,
    'application/vnd.ms-excel': <FileSpreadsheetIcon className="size-6" />,
    'text/csv': <FileSpreadsheetIcon className="size-6" />,
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': (
        <FileSpreadsheetIcon className="size-6" />
    ),
    'application/zip': <FileArchiveIcon className="size-6" />,
    'application/json': <FileCodeIcon className="size-6" />,
};

function fallbackIconFor(mediaType: string): React.ReactNode {
    if (mediaTypeIcons[mediaType]) {
        return mediaTypeIcons[mediaType];
    }

    return <FileIcon className="size-6" />;
}


const AttachmentItem = memo(({ attachment, onRemove }: AttachmentItemProps) => {
    const handleRemove = useCallback(
        () => onRemove?.(attachment.id),
        [onRemove, attachment.id],
    );

    return (
        <Tooltip key={attachment.id} value={attachment.filename}>
            <Attachment
                data={attachment}
                key={attachment.id}
                onRemove={handleRemove}
                className="relative"
            >
                <AttachmentPreview
                    fallbackIcon={fallbackIconFor(attachment.mediaType)}
                />
                {onRemove && <AttachmentRemove />}
            </Attachment>
        </Tooltip>
    );
});



export default AttachmentItem;
