import { ChartColumnBigIcon } from 'lucide-react';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Line, LineChart, Pie, PieChart, XAxis, YAxis } from 'recharts';
import ArtifactFrame from '@/components/ai/artifacts/artifact-frame';
import { formatCell, formatNumber, useConversationId } from '@/components/ai/artifacts/format';
import type { ChartConfig } from '@/components/ui/chart';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import { chartColor } from '@/lib/utils';
import type { ChartData, ChartToolUIPart } from '@/types/agent';
import { chart } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: ChartToolUIPart;
    enabled: boolean;
};

/**
 * The key of a series in the chart: column names like "SUM(total)" cannot be used in the CSS variables of the colors.
 */
const seriesKey = (index: number): string => `series${index}`;

export default function ChartPreview({ part, enabled }: Props) {
    const conversationId = useConversationId();
    const { chartType, xColumn, yColumns, stacked, title, totalRows } = part.output;

    const { ref, data, error, processing } = useToolArtifact<ChartData>(
        chart.url({ conversation: conversationId, toolCallId: part.toolCallId }),
        enabled,
    );

    const config: ChartConfig = Object.fromEntries(
        yColumns.map((column, index) => [
            seriesKey(index),
            { label: column, color: chartColor(index) },
        ]),
    );

    const points = (data?.rows ?? []).map((row, rowIndex) => ({
        x: formatCell(row[xColumn]),
        fill: chartColor(rowIndex),
        ...Object.fromEntries(
            yColumns.map((column, index) => [
                seriesKey(index),
                Number(row[column] ?? 0),
            ]),
        ),
    }));

    const axes = (
        <>
            <CartesianGrid vertical={false} />
            <XAxis dataKey="x" tickLine={false} axisLine={false} tickMargin={8} minTickGap={16} />
            <YAxis tickLine={false} axisLine={false} width={56} tickFormatter={(value: number) => formatNumber(value)} />
            <ChartTooltip content={<ChartTooltipContent />} />
            {yColumns.length > 1 && <ChartLegend content={<ChartLegendContent />} />}
        </>
    );

    const renderChart = () => {
        switch (chartType) {
            case 'line':
                return (
                    <LineChart data={points} accessibilityLayer>
                        {axes}
                        {yColumns.map((_, index) => (
                            <Line key={index} dataKey={seriesKey(index)} type="monotone" stroke={`var(--color-${seriesKey(index)})`} strokeWidth={2} dot={points.length <= 31} />
                        ))}
                    </LineChart>
                );
            case 'area':
                return (
                    <AreaChart data={points} accessibilityLayer>
                        {axes}
                        {yColumns.map((_, index) => (
                            <Area
                                key={index}
                                dataKey={seriesKey(index)}
                                type="monotone"
                                stackId={stacked ? 'stack' : undefined}
                                stroke={`var(--color-${seriesKey(index)})`}
                                fill={`var(--color-${seriesKey(index)})`}
                                fillOpacity={0.3}
                            />
                        ))}
                    </AreaChart>
                );
            case 'pie':
                return (
                    <PieChart>
                        <ChartTooltip content={<ChartTooltipContent nameKey="x" hideLabel />} />
                        <Pie data={points} dataKey={seriesKey(0)} nameKey="x" innerRadius="45%" strokeWidth={2} label={({ name }) => name} />
                    </PieChart>
                );
            default:
                return (
                    <BarChart data={points} accessibilityLayer>
                        {axes}
                        {yColumns.map((_, index) => (
                            <Bar key={index} dataKey={seriesKey(index)} stackId={stacked ? 'stack' : undefined} fill={`var(--color-${seriesKey(index)})`} radius={4} />
                        ))}
                    </BarChart>
                );
        }
    };

    return (
        <ArtifactFrame
            ref={ref}
            icon={ChartColumnBigIcon}
            title={title ?? `${yColumns.join(', ')} par ${xColumn}`}
            description={data?.truncated ? `${formatNumber(points.length)} premiers points sur ${formatNumber(totalRows)}` : undefined}
            data={data}
            error={error}
            processing={processing}
            skeletonClassName="aspect-video h-auto"
            className="p-4"
        >
            {points.length === 0 ? (
                <p className="p-6 text-center text-sm text-muted-foreground">Aucune donnée à afficher.</p>
            ) : (
                <ChartContainer config={config} className="max-h-96 w-full">
                    {renderChart()}
                </ChartContainer>
            )}
        </ArtifactFrame>
    );
}
