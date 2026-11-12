import { useHttp } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChartNoAxesColumnIcon, LoaderIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import FeatureUsageCard from '@/components/settings/usage/feature-usage-card';
import { formatPointDate, totalTokens } from '@/components/settings/usage/format';
import ProviderUsageCard from '@/components/settings/usage/provider-usage-card';
import UsageStatTiles from '@/components/settings/usage/usage-stat-tiles';
import UsageTimelineChart, { providerSeries, seriesColor } from '@/components/settings/usage/usage-timeline-chart';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { UsagePeriod, UsageStatistics } from '@/types/models/usage';
import UsageController from '@/wayfinder/actions/App/Http/Controllers/UsageController';

const PERIODS: UsagePeriod[] = ['7', '30', '90', '365', 'all'];

/**
 * The usage of each model of each provider, by every agent of the application, over a period.
 */
export default function UsageSettings() {
    const { t } = useLaravelReactI18n();
    const [period, setPeriod] = useState<UsagePeriod>('30');
    const [statistics, setStatistics] = useState<UsageStatistics | null>(null);

    const loader = useHttp<null, { data: UsageStatistics }>();

    useEffect(() => {
        loader.get(UsageController.url({ query: { period } }), {
            onSuccess: ({ data }) => setStatistics(data),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [period]);

    const providerColors = useMemo(() => {
        const series = providerSeries(statistics?.providers ?? []);

        return new Map(series.flatMap((item, index) => item.ids.map((id) => [id, seriesColor(index)] as const)));
    }, [statistics?.providers]);

    if (statistics === null) {
        return (
            <div className="flex justify-center py-8">
                <LoaderIcon className="animate-spin" />
            </div>
        );
    }

    const tokens = totalTokens(statistics.totals);

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                    {t('settings.usage.period_range', {
                        from: formatPointDate(statistics.period.from, true),
                        to: formatPointDate(statistics.period.to, true),
                    })}
                    {loader.processing && <LoaderIcon className="ml-2 inline size-3.5 animate-spin" />}
                </p>
                <Tabs value={period} onValueChange={(value) => setPeriod(value as UsagePeriod)}>
                    <TabsList aria-label={t('settings.usage.period')}>
                        {PERIODS.map((item) => (
                            <TabsTrigger key={item} value={item} className="px-3">
                                {t(`settings.usage.periods.${item}`)}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>
            </div>

            {statistics.totals.requests === 0 ? (
                <EmptyState
                    icon={ChartNoAxesColumnIcon}
                    title={t('settings.usage.empty')}
                    description={t('settings.usage.empty_description')}
                />
            ) : (
                <>
                    <UsageStatTiles statistics={statistics} />
                    <UsageTimelineChart statistics={statistics} />
                    {statistics.providers.map((provider) => (
                        <ProviderUsageCard
                            key={provider.id}
                            provider={provider}
                            color={providerColors.get(provider.id) ?? seriesColor(0)}
                            share={tokens > 0 ? totalTokens(provider) / tokens : 0}
                        />
                    ))}
                    <FeatureUsageCard features={statistics.features} />
                </>
            )}
        </div>
    );
}
