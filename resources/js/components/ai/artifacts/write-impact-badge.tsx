import { LoaderIcon } from 'lucide-react';
import { formatNumber, useConversationId } from '@/components/ai/artifacts/format';
import { Badge } from '@/components/reui/badge';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import type { WritePreview } from '@/types/agent';
import { writePreview } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

/**
 * The number of rows a WriteQuery waiting for approval would change, counted by running it in a transaction rolled back.
 */
export default function WriteImpactBadge({ toolCallId }: { toolCallId: string }) {
    const conversationId = useConversationId();

    const { ref, data, error, processing } = useToolArtifact<WritePreview>(
        writePreview.url({ conversation: conversationId, toolCallId }),
    );

    if (error || (data && data.affectedRows === null)) {
        return <span ref={ref} />;
    }

    return (
        <span ref={ref}>
            {processing || !data ? (
                <LoaderIcon className="size-3.5 animate-spin text-muted-foreground" />
            ) : (
                <Badge variant={data.affectedRows === 0 ? 'secondary' : 'warning-light'}>
                    {data.affectedRows === 0
                        ? 'Aucune ligne affectée'
                        : `${formatNumber(data.affectedRows ?? 0)} ligne${(data.affectedRows ?? 0) > 1 ? 's' : ''} affectée${(data.affectedRows ?? 0) > 1 ? 's' : ''}`}
                </Badge>
            )}
        </span>
    );
}
