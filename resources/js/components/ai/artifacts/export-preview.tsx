import { DownloadIcon } from 'lucide-react';
import { useConversationId } from '@/components/ai/artifacts/format';
import FileRow, { fileExtension } from '@/components/ai/file-row';
import { Badge } from '@/components/reui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ExportDatabaseToolUIPart, ExportFormat } from '@/types/agent';
import { download } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: ExportDatabaseToolUIPart;
    /**
     * Whether the tool call is saved: the archive is downloaded from the path saved with it.
     */
    enabled: boolean;
    className?: string;
};

const FORMAT_LABELS: Record<ExportFormat, string> = {
    sql: 'Dump SQL',
    csv: 'CSV',
};

export default function ExportPreview({ part, enabled, className }: Props) {
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
                            {FORMAT_LABELS[format]}
                        </Badge>
                        {tables === 'query' && (
                            <Badge variant="success-light" size="sm">
                                {rows ?? 0} {rows === 1 ? 'ligne' : 'lignes'}
                            </Badge>
                        )}
                        {tables === 'all' && (
                            <Badge variant="success-light" size="sm">
                                Toutes les tables
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
                            Télécharger
                        </a>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            <DownloadIcon />
                            Télécharger
                        </Button>
                    )
                }
            />
        </div>
    );
}
