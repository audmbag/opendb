import { useLaravelReactI18n } from 'laravel-react-i18n';
import { LayersIcon } from 'lucide-react';
import CardFrame from '@/components/card-frame';
import { formatInteger, formatPercent, formatTokens } from '@/components/settings/usage/format';
import type { FeatureUsage } from '@/types/models/usage';

/**
 * Where the tokens went in the application: the conversations, the assistants of the tables and of the SQL editor.
 */
export default function FeatureUsageCard({ features }: { features: FeatureUsage[] }) {
    const { t, tChoice } = useLaravelReactI18n();
    const total = features.reduce((sum, feature) => sum + feature.tokens, 0);
    const largest = features[0]?.tokens ?? 0;

    return (
        <CardFrame title={t('settings.usage.by_feature')} description={t('settings.usage.by_feature_description')} icon={LayersIcon}>
            <ul className="flex flex-col gap-3">
                {features.map((feature) => (
                    <li key={feature.id} className="flex flex-col gap-1">
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="truncate">{t(`ai.features.${feature.feature}`)}</span>
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                <span className="font-medium text-foreground">{formatTokens(feature.tokens)}</span>
                                {total > 0 && ` · ${formatPercent(feature.tokens / total)}`} ·{' '}
                                {tChoice('settings.usage.requests', feature.requests, { count: formatInteger(feature.requests) })}
                            </span>
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-(--chart-1)"
                                style={{ width: `${largest > 0 ? (feature.tokens / largest) * 100 : 0}%` }}
                            />
                        </div>
                    </li>
                ))}
            </ul>
        </CardFrame>
    );
}
