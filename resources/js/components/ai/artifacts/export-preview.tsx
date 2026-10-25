import { DownloadIcon, FileArchiveIcon } from 'lucide-react';
import {
    formatBytes,
    useConversationId,
} from '@/components/ai/artifacts/format';
import CardFrame from '@/components/card-frame';
import { Badge } from '@/components/reui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import type {
    ExportCompression,
    ExportDatabaseToolUIPart,
    ExportFormat,
} from '@/types/agent';
import { download } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: ExportDatabaseToolUIPart;
    /**
     * Whether the tool call is saved: the archive is downloaded from the path saved with it.
     */
    enabled: boolean;
};

const FORMAT_LABELS: Record<ExportFormat, string> = {
    sql: 'Dump SQL',
    csv: 'CSV',
};

const COMPRESSION_LABELS: Record<ExportCompression, string> = {
    zip: 'ZIP',
    tar: 'TAR',
    gzip: 'GZIP',
};

export default function ExportPreview({ part, enabled }: Props) {
    const conversationId = useConversationId();

    if (!part.output?.success) {
        return null;
    }

    const { path, format, output, tables, bytes } = part.output;
    const fileName = path.split('/').pop() ?? path;

    return (
        <div className="w-full max-w-228 min-w-0">
            <CardFrame
                title={fileName}
                description={`${FORMAT_LABELS[format]} · ${COMPRESSION_LABELS[output]} · ${formatBytes(bytes)}`}
                icon={FileArchiveIcon}
                actions={
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
            >
                <div className="flex flex-col gap-2 text-sm">
                    <p className="text-muted-foreground">
                        Enregistré dans{' '}
                        <code className="font-mono text-foreground">
                            {path}
                        </code>
                    </p>
                    {tables === 'all' ? (
                        <p className="text-muted-foreground">
                            Toutes les tables, avec leur schéma et leurs
                            données.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-1.5">
                            <p className="text-muted-foreground">
                                Tables sélectionnées :
                            </p>
                            {tables.map((table) => (
                                <Badge key={table} variant="success-light">
                                    {table}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            </CardFrame>
        </div>
    );
}
