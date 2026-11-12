import { usePage } from '@inertiajs/react';
import { trans } from '@/lib/i18n';
import { numberFormatter } from '@/lib/intl';
import type { CellValue, KpiFormat } from '@/types/agent';
import type { Conversation } from '@/types/models/conversation';


/**
 * The id of the conversation shown on the page, which owns the tool calls of the artifacts.
 */
export function useConversationId(): string {
    return usePage<{ conversation: Conversation }>().props.conversation.id;
}

export function formatNumber(value: number): string {
    return numberFormatter({ maximumFractionDigits: 2 }).format(value);
}

/**
 * A size in bytes with the largest unit that keeps it above 1 (o, Ko, Mo, Go in French; B, KB, MB, GB in English).
 */
export function formatBytes(bytes: number): string {
    const units = ['byte', 'kilobyte', 'megabyte', 'gigabyte'].map((unit) => trans(`common.units.${unit}`));
    const exponent = Math.min(units.length - 1, bytes > 0 ? Math.floor(Math.log(bytes) / Math.log(1024)) : 0);

    return `${formatNumber(bytes / 1024 ** exponent)} ${units[exponent]}`;
}

/**
 * A value of a cell as displayed in a table: NULL is handled by the caller, objects are shown as JSON.
 */
export function formatCell(value: CellValue | object): string {
    if (value === null) {
        return 'NULL';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function formatDuration(seconds: number): string {
    const units: [string, number][] = [[trans('common.units.day'), 86400], ['h', 3600], ['min', 60], ['s', 1]];

    const parts = units
        .map(([unit, size]) => {
            const amount = Math.floor(seconds / size);
            seconds -= amount * size;

            return amount > 0 ? `${amount} ${unit}` : null;
        })
        .filter(Boolean)
        .slice(0, 2);

    return parts.length > 0 ? parts.join(' ') : '0 s';
}

/**
 * An amount in the currency given by the agent, or a plain number when it gave none or an unknown one.
 */
function formatCurrency(amount: number, currency: string | null): string {
    if (!currency) {
        return formatNumber(amount);
    }

    try {
        return numberFormatter({ style: 'currency', currency }).format(amount);
    } catch {
        return `${formatNumber(amount)} ${currency}`;
    }
}

export function formatKpi(value: CellValue, format: KpiFormat, currency: string | null = null): string {
    const number = Number(value);

    if (value === null || value === '' || Number.isNaN(number)) {
        return value === null ? '—' : String(value);
    }

    switch (format) {
        case 'currency':
            return formatCurrency(number, currency);
        case 'percent':
            return numberFormatter({ style: 'percent', maximumFractionDigits: 2 }).format(number / 100);
        case 'duration':
            return formatDuration(number);
        default:
            return formatNumber(number);
    }
}
