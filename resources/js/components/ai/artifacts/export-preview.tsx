import { useLaravelReactI18n } from 'laravel-react-i18n';
import { DownloadIcon } from 'lucide-react';
import { useConversationId } from '@/components/ai/artifacts/format';
import FileRow, { fileExtension } from '@/components/ai/file-row';
import { Badge } from '@/components/reui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ExportDatabaseToolUIPart } from '@/types/agent';
import { download } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: ExportDatabaseToolUIPart;
    /**
     * Whether the tool call is saved: the archive is downloaded from the path saved with it.
     */
    enabled: boolean;
    className?: string;
};

export default function ExportPreview({ part, enabled, className }: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const conversationId = useConversationId();

    if (!part.output?.success) {
        return null;
    }

    const { path, format, tables, rows, bytes, modified_at } = part.output;
    const fileName = path.split('/').pop() ?? path;

    return (
        <div className="w-full max-w-228 min-w-0">
            <FileRow
                className={cn('bg-card', className)}
                file={{
                    path,
                    name: fileName,
                    extension: fileExtension(fileName),
                    size: bytes,
                    modified_at: modified_at ?? null,
                    error: null,
                }}
                badges={
                    <>
                        <Badge variant="outline" size="sm">
                            {t(`exports.short_formats.${format}`)}
                        </Badge>
                        {tables === 'query' && (
                            <Badge variant="success-light" size="sm">
                                {tChoice('tables.counts.rows', rows ?? 0)}
                            </Badge>
                        )}
                        {tables === 'all' && (
                            <Badge variant="success-light" size="sm">
                                {t('exports.all_tables')}
                            </Badge>
                        )}
                        {Array.isArray(tables) &&
                            tables.map((table) => (
                                <Badge
                                    key={table}
                                    variant="success-light"
                                    size="sm"
                                >
                                    {table}
                                </Badge>
                            ))}
                    </>
                }
                action={
                    enabled ? (
                        <a
                            className={buttonVariants({
                                variant: 'outline',
                                size: 'sm',
                            })}
                            href={download.url({
                                conversation: conversationId,
                                toolCallId: part.toolCallId,
                            })}
                            download={fileName}
                        >
                            <DownloadIcon />
                            {t('common.download')}
                        </a>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            <DownloadIcon />
                            {t('common.download')}
                        </Button>
                    )
                }
            />
        </div>
    );
}
