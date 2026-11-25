import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import { formatCell, formatNumber } from '@/components/ai/artifacts/format';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { chartColor, cn } from '@/lib/utils';
import type { CellValue, ChartType } from '@/types/agent';

export type ChartViewProps = {
    chartType: ChartType;
    xColumn: string;
    yColumns: string[];
    stacked: boolean;
    rows: Record<string, CellValue>[];
    className?: string;
};

/**
 * The key of a series in the chart: column names like "SUM(total)" cannot be used in the CSS variables of the colors.
 */
const seriesKey = (index: number): string => `series${index}`;

/**
 * A chart of rows, one point per row: the values of the X column on the axis, one series per Y column. Drawn the
 * same way for the charts of the chat and the results of the SQL editor.
 */
export default function ChartView({
    chartType,
    xColumn,
    yColumns,
    stacked,
    rows,
    className,
}: ChartViewProps) {
    const { t } = useLaravelReactI18n();
    const config: ChartConfig = Object.fromEntries(
        yColumns.map((column, index) => [
            seriesKey(index),
            { label: column, color: chartColor(index) },
        ]),
    );

    // A color per point only for the slices of a pie: Recharts would also paint each bar with it, unlike its series.
    const points = rows.map((row, rowIndex) => ({
        x: formatCell(row[xColumn]),
        ...(chartType === 'pie' && { fill: chartColor(rowIndex) }),
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
            <XAxis
                dataKey="x"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                minTickGap={16}
            />
            <YAxis
                tickLine={false}
                axisLine={false}
                width={56}
                tickFormatter={(value: number) => formatNumber(value)}
            />
            <ChartTooltip content={<ChartTooltipContent />} />
            {yColumns.length > 1 && (
                <ChartLegend content={<ChartLegendContent />} />
            )}
        </>
    );

    const renderChart = () => {
        switch (chartType) {
            case 'line':
                return (
                    <LineChart data={points} accessibilityLayer>
                        {axes}
                        {yColumns.map((_, index) => (
                            <Line
                                key={index}
                                dataKey={seriesKey(index)}
                                type="monotone"
                                stroke={`var(--color-${seriesKey(index)})`}
                                strokeWidth={2}
                                dot={points.length <= 31}
                            />
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
                        <ChartTooltip
                            content={
                                <ChartTooltipContent nameKey="x" hideLabel />
                            }
                        />
                        <Pie
                            data={points}
                            dataKey={seriesKey(0)}
                            nameKey="x"
                            innerRadius="45%"
                            strokeWidth={2}
                            label={({ name }) => name}
                        />
                    </PieChart>
                );
            default:
                return (
                    <BarChart data={points} accessibilityLayer>
                        {axes}
                        {yColumns.map((_, index) => (
                            <Bar
                                key={index}
                                dataKey={seriesKey(index)}
                                stackId={stacked ? 'stack' : undefined}
                                fill={`var(--color-${seriesKey(index)})`}
                                radius={4}
                            />
                        ))}
                    </BarChart>
                );
        }
    };

    if (points.length === 0) {
        return (
            <p className="p-6 text-center text-sm text-muted-foreground">
                {t('ai.artifacts.no_data')}
            </p>
        );
    }

    return (
        <ChartContainer
            config={config}
            className={cn('max-h-96 w-full', className)}
        >
            {renderChart()}
        </ChartContainer>
    );
}
