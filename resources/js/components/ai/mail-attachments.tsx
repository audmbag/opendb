import { useHttp } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { PaperclipIcon, TriangleAlertIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { formatBytes } from '@/components/ai/artifacts/format';
import type { DescribedFile } from '@/components/ai/file-row';
import FileRow from '@/components/ai/file-row';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { trans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import MailFormController from '@/wayfinder/actions/App/Http/Controllers/Chat/MailFormController';

export type MailAttachmentFile = DescribedFile;

type Props = {
    conversationId: string;
    toolCallId: string;
    /**
     * Paths of the attachments the user turned off: they stay listed but are not sent.
     */
    disabled: string[];
    onToggle: (path: string, enabled: boolean) => void;
    /**
     * Called once the files are described, so the attachments that would be refused can be left out of the mail.
     */
    onLoaded?: (files: MailAttachmentFile[]) => void;
    error?: string;
};

const AttachmentRow = ({
    file,
    enabled,
    onToggle,
}: {
    file: MailAttachmentFile;
    enabled: boolean;
    onToggle: (enabled: boolean) => void;
}) => {
    const { t } = useLaravelReactI18n();
    const id = `attachment-${file.path}`;

    return (
        <FileRow
            file={file}
            inactive={!enabled}
            labelFor={id}
            action={
                <Switch
                    id={id}
                    checked={enabled}
                    disabled={file.error !== null}
                    onCheckedChange={onToggle}
                    aria-label={enabled ? t('mail.detach', { file: file.name }) : t('mail.attach', { file: file.name })}
                />
            }
        />
    );
};

export default function MailAttachments({ conversationId, toolCallId, disabled, onToggle, onLoaded, error }: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const [files, setFiles] = useState<MailAttachmentFile[] | null>(null);
    const [maxTotalBytes, setMaxTotalBytes] = useState<number | null>(null);
    const [loadingError, setLoadingError] = useState<string | null>(null);

    const request = useHttp<null, { data: { files: MailAttachmentFile[]; maxTotalBytes: number } }>();

    useEffect(() => {
        request.get(MailFormController.attachments.url({ conversation: conversationId, toolCallId }), {
            onSuccess: ({ data }) => {
                setFiles(data.files);
                setMaxTotalBytes(data.maxTotalBytes);
                onLoaded?.(data.files);
            },
            onHttpException: () => {
                setLoadingError(trans('mail.attachments_failed'));

                return false;
            },
        }).then(() => null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [conversationId, toolCallId]);

    const isEnabled = (file: MailAttachmentFile) => file.error === null && !disabled.includes(file.path);
    const enabledFiles = (files ?? []).filter(isEnabled);
    const totalBytes = enabledFiles.reduce((total, file) => total + (file.size ?? 0), 0);
    const exceedsLimit = maxTotalBytes !== null && totalBytes > maxTotalBytes;

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-1.5 text-sm font-medium">
                    <PaperclipIcon className="size-4 text-muted-foreground" />
                    {t('mail.attachments')}
                </span>
                {files && (
                    <span className={cn('text-xs text-muted-foreground', exceedsLimit && 'text-destructive')}>
                        {tChoice('mail.attached_count', enabledFiles.length, { total: files.length })} · {formatBytes(totalBytes)}
                        {maxTotalBytes !== null && ` ${t('mail.max_size', { size: formatBytes(maxTotalBytes) })}`}
                    </span>
                )}
            </div>

            {loadingError && (
                <p className="flex items-center gap-2 text-sm text-destructive">
                    <TriangleAlertIcon className="size-4" />
                    {loadingError}
                </p>
            )}

            {!files && !loadingError && <Skeleton className="h-16 w-full" />}

            {files?.map((file) => (
                <AttachmentRow
                    key={file.path}
                    file={file}
                    enabled={isEnabled(file)}
                    onToggle={(enabled) => onToggle(file.path, enabled)}
                />
            ))}

            {error && (
                <p className="flex items-center gap-2 text-sm text-destructive">
                    <TriangleAlertIcon className="size-4" />
                    {error}
                </p>
            )}
        </div>
    );
}
