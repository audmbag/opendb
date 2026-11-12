import { formatCell } from '@/components/ai/artifacts/format';
import { transChoice } from '@/lib/i18n';
import { numberFormatter } from '@/lib/intl';
import type { CellValue, ColumnProfile } from '@/types/agent';

/**
 * The rate of NULL above which a column needs attention, and the one from which it is critical.
 */
export const WARN_THRESHOLD = 20;

export const DANGER_THRESHOLD = 50;

/**
 * The number of distinct values up to which a column that is not unique is categorical.
 */
const CATEGORICAL_LIMIT = 10;

export type NullSeverity = 'none' | 'low' | 'warn' | 'danger';

export type DerivedColumn = ColumnProfile & {
    nullRate: number;
    filled: number;
    severity: NullSeverity;
    needsAttention: boolean;
    isUnique: boolean;
    cardinality: 'unique' | 'categorical' | null;
    distinctRate: number | null;
    hasRepetition: boolean;
};

export function formatInteger(value: number): string {
    return numberFormatter({ maximumFractionDigits: 0 }).format(value);
}

/**
 * A rate between 0 and 100 with at most one decimal, dropped when it is whole: « 0 % », « 38,2 % », « 100 % ».
 */
export function formatPercent(rate: number): string {
    return numberFormatter({ style: 'percent', minimumFractionDigits: 0, maximumFractionDigits: 1 }).format(
        Math.round(rate * 10) / 1000,
    );
}

export function formatLength(length: number | null): string {
    return length === null ? '–' : numberFormatter({ minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(length);
}

/**
 * « 0 ligne », « 1 ligne », « 1 000 lignes », in the language of the interface.
 */
export function formatRows(count: number): string {
    return transChoice('tables.counts.rows', count, { count: formatInteger(count) });
}

/**
 * A minimum, maximum or frequent value as displayed in the profile, a dash when it was not computed.
 */
export function formatValue(value: CellValue): string {
    return value === null ? '–' : formatCell(value);
}

export function nullSeverity(rate: number): NullSeverity {
    if (rate >= DANGER_THRESHOLD) {
        return 'danger';
    }

    if (rate >= WARN_THRESHOLD) {
        return 'warn';
    }

    return rate > 0 ? 'low' : 'none';
}

/**
 * The statistics derived from the counts of a column, every rate being computed on the rows actually read.
 */
export function deriveColumn(column: ColumnProfile, sampleSize: number): DerivedColumn {
    const nullRate = sampleSize > 0 ? (column.nullCount / sampleSize) * 100 : 0;
    const filled = sampleSize - column.nullCount;
    const isUnique = filled > 0 && column.distinctCount === filled;
    const isCategorical = !isUnique && column.distinctCount !== null && column.distinctCount <= CATEGORICAL_LIMIT;

    return {
        ...column,
        nullRate,
        filled,
        severity: nullSeverity(nullRate),
        needsAttention: nullRate > WARN_THRESHOLD,
        isUnique,
        cardinality: isUnique ? 'unique' : isCategorical ? 'categorical' : null,
        distinctRate: column.distinctCount !== null && filled > 0 ? (column.distinctCount / filled) * 100 : null,
        hasRepetition: (column.topValues[0]?.count ?? 0) > 1,
    };
}

export function averageNullRate(columns: DerivedColumn[]): number {
    return columns.length > 0 ? columns.reduce((total, column) => total + column.nullRate, 0) / columns.length : 0;
}
