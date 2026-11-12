import { useLaravelReactI18n } from 'laravel-react-i18n';
import { formatInteger, formatPercent, formatTokens } from '@/components/settings/usage/format';
import type { UsageTotals } from '@/types/models/usage';

const segmentsOf = (usage: UsageTotals) => [
    { key: 'input', label: 'settings.usage.segments.input', value: usage.input_tokens - usage.cache_read_input_tokens, color: 'var(--chart-1)' },
    { key: 'cache', label: 'settings.usage.segments.cache', value: usage.cache_read_input_tokens, color: 'var(--chart-2)' },
    { key: 'output', label: 'settings.usage.segments.output', value: usage.output_tokens, color: 'var(--chart-3)' },
];

/**
 * How the tokens split between the input sent, the input read from the cache of the provider and the output written.
 */
export default function TokenSplitBar({ usage }: { usage: UsageTotals }) {
    const { t } = useLaravelReactI18n();
    const segments = segmentsOf(usage).filter((segment) => segment.value > 0);
    const total = segments.reduce((sum, segment) => sum + segment.value, 0);

    if (total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2">
            <div className="flex h-2 w-full gap-0.5 overflow-hidden rounded-full" role="img" aria-label={t('settings.usage.token_split')}>
                {segments.map((segment) => (
                    <div
                        key={segment.key}
                        className="h-full first:rounded-l-full last:rounded-r-full"
                        style={{ width: `${(segment.value / total) * 100}%`, backgroundColor: segment.color }}
                        title={t('settings.usage.segment_tokens', { segment: t(segment.label), count: formatInteger(segment.value) })}
                    />
                ))}
            </div>
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {segments.map((segment) => (
                    <span key={segment.key} className="flex items-center gap-1.5">
                        <span className="size-2 rounded-xs" style={{ backgroundColor: segment.color }} />
                        {t(segment.label)}
                        <span className="font-medium text-foreground tabular-nums">{formatTokens(segment.value)}</span>
                        <span className="tabular-nums">({formatPercent(segment.value / total)})</span>
                    </span>
                ))}
                {usage.reasoning_tokens > 0 && (
                    <span>
                        {t('settings.usage.of_which_reasoning')}{' '}
                        <span className="font-medium text-foreground tabular-nums">{formatTokens(usage.reasoning_tokens)}</span>
                    </span>
                )}
            </div>
        </div>
    );
}
