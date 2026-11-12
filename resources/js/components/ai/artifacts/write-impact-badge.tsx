import { useLaravelReactI18n } from 'laravel-react-i18n';
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
    const { t, tChoice } = useLaravelReactI18n();
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
                        ? t('ai.artifacts.no_affected_rows')
                        : tChoice('ai.artifacts.affected_rows', data.affectedRows ?? 0, { count: formatNumber(data.affectedRows ?? 0) })}
                </Badge>
            )}
        </span>
    );
}
