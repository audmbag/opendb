import { useHttp } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarClockIcon,
    FileArchiveIcon,
    FileCodeIcon,
    FileIcon,
    FileImageIcon,
    FileJsonIcon,
    FileSpreadsheetIcon,
    FileTextIcon,
    FileWarningIcon,
    HardDriveIcon,
    PaperclipIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { formatBytes } from '@/components/ai/artifacts/format';
import { Badge } from '@/components/reui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import MailFormController from '@/wayfinder/actions/App/Http/Controllers/Chat/MailFormController';

export type MailAttachmentFile = {
    path: string;
    name: string;
    extension: string;
    size: number | null;
    modified_at: string | null;
    error: string | null;
};

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

type FileKind = {
    label: string;
    icon: LucideIcon;
    className: string;
};

const ARCHIVE: Omit<FileKind, 'label'> = { icon: FileArchiveIcon, className: 'bg-amber-500/10 text-amber-600' };

const FILE_KINDS: Record<string, FileKind> = {
    zip: { label: 'Archive ZIP', ...ARCHIVE },
    tar: { label: 'Archive TAR', ...ARCHIVE },
    'tar.gz': { label: 'Archive TAR.GZ', ...ARCHIVE },
    gz: { label: 'Archive GZIP', ...ARCHIVE },
    'sql.gz': { label: 'Dump SQL compressé', ...ARCHIVE },
    'csv.gz': { label: 'CSV compressé', ...ARCHIVE },
    sql: { label: 'Dump SQL', icon: FileCodeIcon, className: 'bg-sky-500/10 text-sky-600' },
    csv: { label: 'Tableur CSV', icon: FileSpreadsheetIcon, className: 'bg-emerald-500/10 text-emerald-600' },
    xlsx: { label: 'Classeur Excel', icon: FileSpreadsheetIcon, className: 'bg-emerald-500/10 text-emerald-600' },
    json: { label: 'JSON', icon: FileJsonIcon, className: 'bg-violet-500/10 text-violet-600' },
    pdf: { label: 'Document PDF', icon: FileTextIcon, className: 'bg-red-500/10 text-red-600' },
    txt: { label: 'Texte', icon: FileTextIcon, className: 'bg-slate-500/10 text-slate-600' },
    md: { label: 'Markdown', icon: FileTextIcon, className: 'bg-slate-500/10 text-slate-600' },
    png: { label: 'Image PNG', icon: FileImageIcon, className: 'bg-pink-500/10 text-pink-600' },
    jpg: { label: 'Image JPEG', icon: FileImageIcon, className: 'bg-pink-500/10 text-pink-600' },
    jpeg: { label: 'Image JPEG', icon: FileImageIcon, className: 'bg-pink-500/10 text-pink-600' },
};

const fileKind = (file: MailAttachmentFile): FileKind => {
    if (file.error) {
        return { label: 'Indisponible', icon: FileWarningIcon, className: 'bg-destructive/10 text-destructive' };
    }

    return FILE_KINDS[file.extension] ?? {
        label: file.extension ? `Fichier .${file.extension}` : 'Fichier',
        icon: FileIcon,
        className: 'bg-muted text-muted-foreground',
    };
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });

const AttachmentRow = ({
    file,
    enabled,
    onToggle,
}: {
    file: MailAttachmentFile;
    enabled: boolean;
    onToggle: (enabled: boolean) => void;
}) => {
    const kind = fileKind(file);
    const id = `attachment-${file.path}`;

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-md border p-3 transition-opacity',
                !enabled && 'opacity-60',
                file.error && 'border-destructive/40',
            )}
        >
            <div className={cn('flex size-10 shrink-0 items-center justify-center rounded-md', kind.className)}>
                <kind.icon className="size-5" />
            </div>

            <label htmlFor={id} className="flex min-w-0 flex-1 cursor-pointer flex-col gap-1">
                <span className={cn('truncate text-sm font-medium', !enabled && 'line-through')}>{file.name}</span>
                {file.error ? (
                    <span className="flex items-center gap-1 text-xs text-destructive">
                        <TriangleAlertIcon className="size-3.5 shrink-0" />
                        {file.error}
                    </span>
                ) : (
                    <span className="flex flex-wrap items-center gap-1.5">
                        <Badge variant="secondary" size="sm">{kind.label}</Badge>
                        {file.size !== null && (
                            <Badge variant="outline" size="sm">
                                <HardDriveIcon />
                                {formatBytes(file.size)}
                            </Badge>
                        )}
                        {file.modified_at && (
                            <Badge variant="outline" size="sm">
                                <CalendarClockIcon />
                                {formatDate(file.modified_at)}
                            </Badge>
                        )}
                        <span className="truncate font-mono text-[0.7rem] text-muted-foreground">{file.path}</span>
                    </span>
                )}
            </label>

            <Switch
                id={id}
                checked={enabled}
                disabled={file.error !== null}
                onCheckedChange={onToggle}
                aria-label={enabled ? `Ne pas joindre ${file.name}` : `Joindre ${file.name}`}
            />
        </div>
    );
};

export default function MailAttachments({ conversationId, toolCallId, disabled, onToggle, onLoaded, error }: Props) {
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
                setLoadingError('Impossible de charger les pièces jointes.');

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
                    Pièces jointes
                </span>
                {files && (
                    <span className={cn('text-xs text-muted-foreground', exceedsLimit && 'text-destructive')}>
                        {enabledFiles.length} / {files.length} jointe{enabledFiles.length > 1 ? 's' : ''} · {formatBytes(totalBytes)}
                        {maxTotalBytes !== null && ` sur ${formatBytes(maxTotalBytes)} max.`}
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
