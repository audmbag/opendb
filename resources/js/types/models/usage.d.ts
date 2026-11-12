export type UsagePeriod = '7' | '30' | '90' | '365' | 'all';

export interface UsageTotals {
    /**
     * The calls made to the models: an answer of the agent takes one per step.
     */
    requests: number;
    /**
     * The answers, each made of one or more calls.
     */
    runs: number;
    input_tokens: number;
    output_tokens: number;
    cache_read_input_tokens: number;
    cache_write_input_tokens: number;
    reasoning_tokens: number;
    failures: number;
    /**
     * In milliseconds, null when no call was timed.
     */
    average_duration: number | null;
}

export interface ModelUsage extends UsageTotals {
    id: string;
    label: string;
    last_used_at: string;
}

export interface ProviderUsage extends UsageTotals {
    id: string;
    label: string;
    icon: string;
    last_used_at: string;
    models: ModelUsage[];
}

export interface FeatureUsage {
    id: string;
    /**
     * The feature of the application, a key of ai.features in lang/{locale}/ai.php.
     */
    feature: string;
    requests: number;
    tokens: number;
}

export interface UsageTimelinePoint {
    /**
     * A day (YYYY-MM-DD), or a month (YYYY-MM) for a long period.
     */
    date: string;
    total: number;
    providers: Record<string, number>;
}

export interface UsageStatistics {
    period: { days: number | null; from: string; to: string };
    totals: UsageTotals & { providers: number; models: number };
    previous: UsageTotals | null;
    providers: ProviderUsage[];
    features: FeatureUsage[];
    timeline: { bucket: 'day' | 'month'; points: UsageTimelinePoint[] };
}
