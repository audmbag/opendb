import { useLaravelReactI18n } from 'laravel-react-i18n';
import { formatRelativeTime } from '@/components/ai/artifacts/record/format';
import { Badge } from '@/components/reui/badge';
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from '@/components/reui/frame';
import {
    formatDuration,
    formatInteger,
    formatPercent,
    formatTokens,
    totalTokens,
} from '@/components/settings/usage/format';
import TokenSplitBar from '@/components/settings/usage/token-split-bar';
import type { ProviderUsage } from '@/types/models/usage';

const NumberCell = ({ children, title }: { children: React.ReactNode; title?: string }) => (
    <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums" title={title}>
        {children}
    </td>
);

/**
 * The usage of a provider, and of each of its models, in the order of the tokens they used.
 */
export default function ProviderUsageCard({ provider, color, share }: { provider: ProviderUsage; color: string; share: number }) {
    const { t, tChoice } = useLaravelReactI18n();
    const providerTokens = totalTokens(provider);

    return (
        <Frame>
            <FrameHeader className="flex flex-row items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    <img src={provider.icon} alt="" className="size-5 shrink-0" />
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <FrameTitle className="truncate">{provider.label}</FrameTitle>
                            <Badge variant="secondary">{t('settings.usage.token_share', { share: formatPercent(share) })}</Badge>
                        </div>
                        <FrameDescription className="truncate text-xs">
                            {tChoice('settings.usage.requests', provider.requests, { count: formatInteger(provider.requests) })} ·{' '}
                            {tChoice('settings.usage.models', provider.models.length)} ·{' '}
                            {t('settings.usage.used', { when: formatRelativeTime(provider.last_used_at) ?? '' })}
                        </FrameDescription>
                    </div>
                </div>
                <span className="flex shrink-0 items-center gap-2 text-lg font-semibold tabular-nums" title={t('settings.usage.tokens', { count: formatInteger(providerTokens) })}>
                    <span className="size-2.5 rounded-xs" style={{ backgroundColor: color }} aria-hidden />
                    {formatTokens(providerTokens)}
                </span>
            </FrameHeader>
            <FramePanel className="p-0">
            <div className="border-b p-3">
                <TokenSplitBar usage={provider} />
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="text-xs text-muted-foreground">
                        <tr className="border-b">
                            <th className="px-3 py-2 text-left font-medium">{t('settings.usage.columns.model')}</th>
                            <th className="px-3 py-2 text-right font-medium">{t('settings.usage.columns.requests')}</th>
                            <th className="px-3 py-2 text-right font-medium">{t('settings.usage.columns.input')}</th>
                            <th className="px-3 py-2 text-right font-medium">{t('settings.usage.columns.output')}</th>
                            <th className="px-3 py-2 text-right font-medium">{t('settings.usage.columns.cache')}</th>
                            <th className="px-3 py-2 text-right font-medium">{t('settings.usage.columns.average_duration')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {provider.models.map((model) => {
                            const modelTokens = totalTokens(model);
                            const modelShare = providerTokens > 0 ? modelTokens / providerTokens : 0;

                            return (
                                <tr key={model.id} className="border-b last:border-b-0">
                                    <td className="max-w-56 px-3 py-2">
                                        <div className="flex min-w-0 flex-col gap-1">
                                            <span className="truncate font-medium" title={model.id}>
                                                {model.label}
                                            </span>
                                            <div className="flex items-center gap-2">
                                                <div className="h-1 w-20 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full"
                                                        style={{ width: `${modelShare * 100}%`, backgroundColor: color }}
                                                    />
                                                </div>
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {formatTokens(modelTokens)} · {formatRelativeTime(model.last_used_at)}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <NumberCell title={tChoice('settings.usage.runs', model.runs, { count: formatInteger(model.runs) })}>
                                        {formatInteger(model.requests)}
                                        {model.failures > 0 && (
                                            <span className="block text-xs text-destructive">
                                                {tChoice('settings.usage.failures', model.failures, { count: formatInteger(model.failures) })}
                                            </span>
                                        )}
                                    </NumberCell>
                                    <NumberCell title={t('settings.usage.tokens', { count: formatInteger(model.input_tokens) })}>{formatTokens(model.input_tokens)}</NumberCell>
                                    <NumberCell title={t('settings.usage.tokens', { count: formatInteger(model.output_tokens) })}>
                                        {formatTokens(model.output_tokens)}
                                        {model.reasoning_tokens > 0 && (
                                            <span className="block text-xs text-muted-foreground">
                                                {t('settings.usage.including_reasoning', { tokens: formatTokens(model.reasoning_tokens) })}
                                            </span>
                                        )}
                                    </NumberCell>
                                    <NumberCell title={t('settings.usage.cache_tokens', { count: formatInteger(model.cache_read_input_tokens) })}>
                                        {model.input_tokens > 0 ? formatPercent(model.cache_read_input_tokens / model.input_tokens) : '—'}
                                    </NumberCell>
                                    <NumberCell>{formatDuration(model.average_duration)}</NumberCell>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            </FramePanel>
        </Frame>
    );
}
