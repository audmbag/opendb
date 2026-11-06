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
    TriangleAlertIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { formatBytes } from '@/components/ai/artifacts/format';
import { Badge } from '@/components/reui/badge';
import { cn } from '@/lib/utils';

export type DescribedFile = {
    path: string;
    name: string;
    extension: string;
    size: number | null;
    modified_at: string | null;
    error: string | null;
};

type Props = {
    file: DescribedFile;
    /**
     * Whether the file is set aside: it stays listed, faded and struck through.
     */
    inactive?: boolean;
    /**
     * The id of the control the details are the label of, when the row has one.
     */
    labelFor?: string;
    /**
     * More badges describing the file, after its size and date.
     */
    badges?: ReactNode;
    /**
     * The control displayed at the end of the row.
     */
    action?: ReactNode;
    className?: string;
};

type FileKind = {
    label: string;
    icon: LucideIcon;
    className: string;
};

const ARCHIVE: Omit<FileKind, 'label'> = {
    icon: FileArchiveIcon,
    className: 'bg-amber-500/10 text-amber-600',
};

const FILE_KINDS: Record<string, FileKind> = {
    zip: { label: 'Archive ZIP', ...ARCHIVE },
    tar: { label: 'Archive TAR', ...ARCHIVE },
    'tar.gz': { label: 'Archive TAR.GZ', ...ARCHIVE },
    gz: { label: 'Archive GZIP', ...ARCHIVE },
    'sql.gz': { label: 'Dump SQL compressé', ...ARCHIVE },
    'csv.gz': { label: 'CSV compressé', ...ARCHIVE },
    sql: {
        label: 'Dump SQL',
        icon: FileCodeIcon,
        className: 'bg-sky-500/10 text-sky-600',
    },
    csv: {
        label: 'Tableur CSV',
        icon: FileSpreadsheetIcon,
        className: 'bg-emerald-500/10 text-emerald-600',
    },
    xlsx: {
        label: 'Classeur Excel',
        icon: FileSpreadsheetIcon,
        className: 'bg-emerald-500/10 text-emerald-600',
    },
    json: {
        label: 'JSON',
        icon: FileJsonIcon,
        className: 'bg-violet-500/10 text-violet-600',
    },
    pdf: {
        label: 'Document PDF',
        icon: FileTextIcon,
        className: 'bg-red-500/10 text-red-600',
    },
    txt: {
        label: 'Texte',
        icon: FileTextIcon,
        className: 'bg-slate-500/10 text-slate-600',
    },
    md: {
        label: 'Markdown',
        icon: FileTextIcon,
        className: 'bg-slate-500/10 text-slate-600',
    },
    png: {
        label: 'Image PNG',
        icon: FileImageIcon,
        className: 'bg-pink-500/10 text-pink-600',
    },
    jpg: {
        label: 'Image JPEG',
        icon: FileImageIcon,
        className: 'bg-pink-500/10 text-pink-600',
    },
    jpeg: {
        label: 'Image JPEG',
        icon: FileImageIcon,
        className: 'bg-pink-500/10 text-pink-600',
    },
};

const fileKind = (file: DescribedFile): FileKind => {
    if (file.error) {
        return {
            label: 'Indisponible',
            icon: FileWarningIcon,
            className: 'bg-destructive/10 text-destructive',
        };
    }

    return (
        FILE_KINDS[file.extension] ?? {
            label: file.extension ? `Fichier .${file.extension}` : 'Fichier',
            icon: FileIcon,
            className: 'bg-muted text-muted-foreground',
        }
    );
};

const formatDate = (value: string): string =>
    new Date(value).toLocaleString('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

/**
 * The extension of a file name, keeping the double extensions of archives (`sql.gz`, `tar.gz`).
 */
export const fileExtension = (name: string): string =>
    name.toLowerCase().match(/\.((?:sql|csv|tar)\.gz|[^.]+)$/)?.[1] ?? '';

export default function FileRow({
    file,
    inactive = false,
    labelFor,
    badges,
    action,
    className,
}: Props) {
    const kind = fileKind(file);
    const details = (
        <>
            <span
                className={cn(
                    'truncate text-sm font-medium',
                    inactive && 'line-through',
                )}
            >
                {file.name}
            </span>
            {file.error ? (
                <span className="flex items-center gap-1 text-xs text-destructive">
                    <TriangleAlertIcon className="size-3.5 shrink-0" />
                    {file.error}
                </span>
            ) : (
                <span className="flex flex-wrap items-center gap-1.5">
                    <Badge variant="secondary" size="sm">
                        {kind.label}
                    </Badge>
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
                    {badges}
                    <span className="truncate text-[0.7rem] text-muted-foreground">
                        {file.path}
                    </span>
                </span>
            )}
        </>
    );

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-md border p-3 transition-opacity',
                inactive && 'opacity-60',
                file.error && 'border-destructive/40',
                className,
            )}
        >
            <div
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-md',
                    kind.className,
                )}
            >
                <kind.icon className="size-5" />
            </div>

            {labelFor ? (
                <label
                    htmlFor={labelFor}
                    className="flex min-w-0 flex-1 cursor-pointer flex-col gap-1"
                >
                    {details}
                </label>
            ) : (
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    {details}
                </div>
            )}

            {action}
        </div>
    );
}
