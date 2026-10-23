import { GaugeIcon, TriangleAlertIcon } from 'lucide-react';
import ArtifactFrame from '@/components/ai/artifacts/artifact-frame';
import { formatKpi, useConversationId } from '@/components/ai/artifacts/format';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import type { KpiCard, KpisToolUIPart } from '@/types/agent';
import { kpis } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';
import CardFrame from '@/components/card-frame';
import { chartColor } from '@/lib/utils';

type Props = {
    part: KpisToolUIPart;
    enabled: boolean;
};

export default function KpisPreview({ part, enabled }: Props) {
    const conversationId = useConversationId();

    const { ref, data, error, processing } = useToolArtifact<KpiCard[]>(
        kpis.url({ conversation: conversationId, toolCallId: part.toolCallId }),
        enabled,
    );

    return (
        <ArtifactFrame
            ref={ref}
            icon={GaugeIcon}
            title="Indicateurs clés"
            data={data}
            error={error}
            processing={processing}
            skeletonClassName="h-24"
        >
            <div className="grid grid-cols-1 gap-2 p-2 sm:grid-cols-2 lg:grid-cols-3">
                {data?.map((card, index) => (
                    <CardFrame
                        key={index}
                        style={{
                            borderTopColor: chartColor(index),
                            borderLeftColor: chartColor(index),
                            borderTopWidth: 2,
                            borderLeftWidth: 2,
                        }}
                    >
                        <div className="flex min-w-0 flex-col gap-1">
                            <span className="truncate text-xs text-muted-foreground">
                                {card.label}
                            </span>
                            {card.error ? (
                                <span
                                    className="flex items-start gap-1.5 text-xs text-destructive"
                                    title={card.error}
                                >
                                    <TriangleAlertIcon className="mt-0.5 size-3.5 shrink-0" />
                                    <span className="line-clamp-2">
                                        {card.error}
                                    </span>
                                </span>
                            ) : (
                                <span className="truncate text-2xl font-semibold tabular-nums">
                                    {formatKpi(
                                        card.value,
                                        card.format,
                                        card.currency,
                                    )}
                                    {card.unit && (
                                        <span className="ml-1 text-sm font-normal text-muted-foreground">
                                            {card.unit}
                                        </span>
                                    )}
                                </span>
                            )}
                        </div>
                    </CardFrame>
                ))}
            </div>
        </ArtifactFrame>
    );
}
