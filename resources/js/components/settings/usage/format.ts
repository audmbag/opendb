import { dateFormatter, numberFormatter } from '@/lib/intl';
import type { UsageTotals } from '@/types/models/usage';

/**
 * « 2,1 M », « 12,4 k »: the tokens are read as orders of magnitude.
 */
export const formatTokens = (value: number): string =>
    numberFormatter({ notation: 'compact', maximumFractionDigits: 1 }).format(value);

/**
 * « 2 141 948 »: the narrow space grouping the thousands in French has no glyph in the font of the interface.
 */
export const formatInteger = (value: number): string => numberFormatter().format(value).replaceAll(' ', ' ');

export const formatPercent = (value: number): string =>
    numberFormatter({ style: 'percent', maximumFractionDigits: 1 }).format(value);

/**
 * « 850 ms », « 2,4 s ».
 */
export const formatDuration = (milliseconds: number | null): string => {
    if (milliseconds === null) {
        return '—';
    }

    return milliseconds < 1000
        ? `${milliseconds} ms`
        : `${numberFormatter({ maximumFractionDigits: 1 }).format(milliseconds / 1000)} s`;
};

/**
 * A point of the timeline, a day (YYYY-MM-DD) or a month (YYYY-MM), read as a local date.
 */
const parsePointDate = (date: string): Date => {
    const [year, month, day = 1] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
};

export const formatPointDate = (date: string, long = false): string => {
    const isMonth = date.length === 7;
    const options: Intl.DateTimeFormatOptions = isMonth
        ? long
            ? { month: 'long', year: 'numeric' }
            : { month: 'short', year: '2-digit' }
        : long
          ? { weekday: 'long', day: 'numeric', month: 'long' }
          : { day: 'numeric', month: 'short' };

    return dateFormatter(options).format(parsePointDate(date));
};

export const totalTokens = (usage: Pick<UsageTotals, 'input_tokens' | 'output_tokens'>): number =>
    usage.input_tokens + usage.output_tokens;

/**
 * The share of the input read from the prompt cache of the provider, null without input.
 */
export const cacheRate = (usage: UsageTotals): number | null =>
    usage.input_tokens > 0 ? usage.cache_read_input_tokens / usage.input_tokens : null;

/**
 * The change from the previous period, null when there is nothing to compare to.
 */
export const change = (current: number, previous: number | undefined): number | null =>
    previous === undefined || previous === 0 ? null : (current - previous) / previous;
