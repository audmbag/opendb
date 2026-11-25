import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChartColumnBigIcon } from 'lucide-react';
import ArtifactFrame from '@/components/ai/artifacts/artifact-frame';
import ChartView from '@/components/ai/artifacts/chart-view';
import {
    formatNumber,
    useConversationId,
} from '@/components/ai/artifacts/format';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import type { ChartData, ChartToolUIPart } from '@/types/agent';
import { chart } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: ChartToolUIPart;
    enabled: boolean;
};

export default function ChartPreview({ part, enabled }: Props) {
    const { t } = useLaravelReactI18n();
    const conversationId = useConversationId();
    const { chartType, xColumn, yColumns, stacked, title, totalRows } =
        part.output;

    const { ref, data, error, processing } = useToolArtifact<ChartData>(
        chart.url({
            conversation: conversationId,
            toolCallId: part.toolCallId,
        }),
        enabled,
    );

    const rows = data?.rows ?? [];

    return (
        <ArtifactFrame
            ref={ref}
            icon={ChartColumnBigIcon}
            title={
                title ??
                t('ai.artifacts.chart_title', {
                    y: yColumns.join(', '),
                    x: xColumn,
                })
            }
            description={
                data?.truncated
                    ? t('ai.artifacts.first_points', {
                          count: formatNumber(rows.length),
                          total: formatNumber(totalRows),
                      })
                    : undefined
            }
            data={data}
            error={error}
            processing={processing}
            skeletonClassName="aspect-video h-auto"
            className="p-4"
        >
            <ChartView
                chartType={chartType}
                xColumn={xColumn}
                yColumns={yColumns}
                stacked={stacked}
                rows={rows}
            />
        </ArtifactFrame>
    );
}
