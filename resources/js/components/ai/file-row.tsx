import { useLaravelReactI18n } from 'laravel-react-i18n';
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
import { dateFormatter } from '@/lib/intl';
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

/**
 * The kinds of files, their label being a key of lang/{locale}/files.php.
 */
const FILE_KINDS: Record<string, FileKind> = {
    zip: { label: 'files.kinds.zip', ...ARCHIVE },
    tar: { label: 'files.kinds.tar', ...ARCHIVE },
    'tar.gz': { label: 'files.kinds.tar_gz', ...ARCHIVE },
    gz: { label: 'files.kinds.gz', ...ARCHIVE },
    'sql.gz': { label: 'files.kinds.sql_gz', ...ARCHIVE },
    'csv.gz': { label: 'files.kinds.csv_gz', ...ARCHIVE },
    sql: {
        label: 'files.kinds.sql',
        icon: FileCodeIcon,
        className: 'bg-sky-500/10 text-sky-600',
    },
    csv: {
        label: 'files.kinds.csv',
        icon: FileSpreadsheetIcon,
        className: 'bg-emerald-500/10 text-emerald-600',
    },
    xlsx: {
        label: 'files.kinds.xlsx',
        icon: FileSpreadsheetIcon,
        className: 'bg-emerald-500/10 text-emerald-600',
    },
    json: {
        label: 'files.kinds.json',
        icon: FileJsonIcon,
        className: 'bg-violet-500/10 text-violet-600',
    },
    pdf: {
        label: 'files.kinds.pdf',
        icon: FileTextIcon,
        className: 'bg-red-500/10 text-red-600',
    },
    txt: {
        label: 'files.kinds.txt',
        icon: FileTextIcon,
        className: 'bg-slate-500/10 text-slate-600',
    },
    md: {
        label: 'files.kinds.md',
        icon: FileTextIcon,
        className: 'bg-slate-500/10 text-slate-600',
    },
    png: {
        label: 'files.kinds.png',
        icon: FileImageIcon,
        className: 'bg-pink-500/10 text-pink-600',
    },
    jpg: {
        label: 'files.kinds.jpeg',
        icon: FileImageIcon,
        className: 'bg-pink-500/10 text-pink-600',
    },
    jpeg: {
        label: 'files.kinds.jpeg',
        icon: FileImageIcon,
        className: 'bg-pink-500/10 text-pink-600',
    },
};

const fileKind = (file: DescribedFile): FileKind => {
    if (file.error) {
        return {
            label: 'files.kinds.unavailable',
            icon: FileWarningIcon,
            className: 'bg-destructive/10 text-destructive',
        };
    }

    return (
        FILE_KINDS[file.extension] ?? {
            label: file.extension ? 'files.kinds.extension' : 'files.kinds.file',
            icon: FileIcon,
            className: 'bg-muted text-muted-foreground',
        }
    );
};

const formatDate = (value: string): string =>
    dateFormatter({ dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));

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
    const { t } = useLaravelReactI18n();
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
                        {t(kind.label, { extension: file.extension })}
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
