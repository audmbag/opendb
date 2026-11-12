import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChartColumnIcon } from 'lucide-react';
import { useMemo } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import CardFrame from '@/components/card-frame';
import { formatPointDate, formatTokens } from '@/components/settings/usage/format';
import type { ChartConfig } from '@/components/ui/chart';
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { trans } from '@/lib/i18n';
import type { ProviderUsage, UsageStatistics } from '@/types/models/usage';

const OTHERS = 'others';

/**
 * The palette has five colors: the sixth provider and the next ones are drawn together.
 */
const MAX_SERIES = 5;

/**
 * The series of the chart, one per provider. A provider keeps its color whatever the period shown: the colors follow
 * the order of the names, not the tokens used.
 */
export const providerSeries = (providers: ProviderUsage[]): { key: string; label: string; ids: string[] }[] => {
    const sorted = [...providers].sort((a, b) => a.id.localeCompare(b.id));
    const shown = sorted.length > MAX_SERIES ? sorted.slice(0, MAX_SERIES - 1) : sorted;
    const folded = sorted.slice(shown.length);

    return [
        ...shown.map((provider) => ({ key: provider.id, label: provider.label, ids: [provider.id] })),
        ...(folded.length > 0 ? [{ key: OTHERS, label: trans('settings.usage.others'), ids: folded.map((provider) => provider.id) }] : []),
    ];
};

export const seriesColor = (index: number): string => `var(--chart-${index + 1})`;

/**
 * The tokens used each day, or each month of a long period, stacked by provider.
 */
export default function UsageTimelineChart({ statistics }: { statistics: UsageStatistics }) {
    const { t } = useLaravelReactI18n();
    const series = useMemo(() => providerSeries(statistics.providers), [statistics.providers, t]);

    const config: ChartConfig = useMemo(
        () => Object.fromEntries(series.map((item, index) => [item.key, { label: item.label, color: seriesColor(index) }])),
        [series],
    );

    const points = useMemo(
        () =>
            statistics.timeline.points.map((point) => ({
                date: point.date,
                ...Object.fromEntries(
                    series.map((item) => [item.key, item.ids.reduce((sum, id) => sum + (point.providers[id] ?? 0), 0)]),
                ),
            })),
        [statistics.timeline.points, series],
    );

    const isMonthly = statistics.timeline.bucket === 'month';

    return (
        <CardFrame
            title={t('settings.usage.timeline')}
            description={isMonthly ? t('settings.usage.timeline_monthly') : t('settings.usage.timeline_daily')}
            icon={ChartColumnIcon}
        >
            <ChartContainer config={config} className="aspect-auto h-56 w-full">
                <BarChart data={points} accessibilityLayer barCategoryGap={points.length > 40 ? 1 : '20%'}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="date"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        minTickGap={24}
                        tickFormatter={(date: string) => formatPointDate(date)}
                    />
                    <YAxis tickLine={false} axisLine={false} width={48} tickFormatter={(value: number) => formatTokens(value)} />
                    <ChartTooltip
                        cursor={{ fillOpacity: 0.4 }}
                        content={
                            <ChartTooltipContent
                                labelFormatter={(_, payload) => formatPointDate(String(payload?.[0]?.payload?.date ?? ''), true)}
                            />
                        }
                    />
                    {series.length > 1 && <ChartLegend content={<ChartLegendContent />} />}
                    {series.map((item, index) => (
                        <Bar
                            key={item.key}
                            dataKey={item.key}
                            stackId="tokens"
                            fill={`var(--color-${item.key})`}
                            stroke="var(--background)"
                            strokeWidth={series.length > 1 ? 1 : 0}
                            radius={index === series.length - 1 ? [4, 4, 0, 0] : 0}
                        />
                    ))}
                </BarChart>
            </ChartContainer>
        </CardFrame>
    );
}
