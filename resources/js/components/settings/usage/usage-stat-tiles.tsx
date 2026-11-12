import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowDownRightIcon, ArrowUpRightIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import CardFrame from '@/components/card-frame';
import {
    cacheRate,
    change,
    formatDuration,
    formatInteger,
    formatPercent,
    formatTokens,
    totalTokens,
} from '@/components/settings/usage/format';
import type { UsageStatistics } from '@/types/models/usage';

const Change = ({ value }: { value: number | null }) => {
    const { t } = useLaravelReactI18n();

    if (value === null) {
        return null;
    }

    const Icon = value >= 0 ? ArrowUpRightIcon : ArrowDownRightIcon;

    return (
        <span className="flex items-center gap-0.5 text-xs text-muted-foreground" title={t('settings.usage.compared_to_previous')}>
            <Icon className="size-3.5" />
            {value >= 0 ? '+' : ''}
            {formatPercent(value)}
        </span>
    );
};

const StatTile = ({ label, value, detail }: { label: string; value: string; detail?: ReactNode }) => (
    <CardFrame>
        <div className="flex min-w-0 flex-col gap-1">
            <span className="truncate text-xs text-muted-foreground">{label}</span>
            <span className="truncate text-2xl font-semibold tabular-nums">{value}</span>
            <div className="flex min-h-4 items-center gap-2 text-xs text-muted-foreground">{detail}</div>
        </div>
    </CardFrame>
);

/**
 * The headline numbers of the period, each compared to the previous one of the same length.
 */
export default function UsageStatTiles({ statistics }: { statistics: UsageStatistics }) {
    const { t, tChoice } = useLaravelReactI18n();
    const { totals, previous } = statistics;
    const tokens = totalTokens(totals);
    const rate = cacheRate(totals);
    const failureRate = totals.requests > 0 ? totals.failures / totals.requests : 0;

    return (
        <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
            <StatTile
                label={t('settings.usage.tiles.tokens')}
                value={formatTokens(tokens)}
                detail={
                    <>
                        <span>{formatInteger(tokens)}</span>
                        <Change value={change(tokens, previous ? totalTokens(previous) : undefined)} />
                    </>
                }
            />
            <StatTile
                label={t('settings.usage.tiles.requests')}
                value={formatInteger(totals.requests)}
                detail={
                    <>
                        <span>
                            {tChoice('settings.usage.runs', totals.runs, { count: formatInteger(totals.runs) })}
                        </span>
                        <Change value={change(totals.requests, previous?.requests)} />
                    </>
                }
            />
            <StatTile
                label={t('settings.usage.tiles.cache')}
                value={rate === null ? '—' : formatPercent(rate)}
                detail={<span>{t('settings.usage.input_tokens', { count: formatTokens(totals.cache_read_input_tokens) })}</span>}
            />
            <StatTile
                label={t('settings.usage.tiles.average_duration')}
                value={formatDuration(totals.average_duration)}
                detail={
                    <span>
                        {totals.failures > 0
                            ? `${tChoice('settings.usage.failures', totals.failures, { count: formatInteger(totals.failures) })} (${formatPercent(failureRate)})`
                            : t('settings.usage.no_failures')}
                    </span>
                }
            />
        </div>
    );
}
