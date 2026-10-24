import { usePage } from '@inertiajs/react';
import type { CellValue, KpiFormat } from '@/types/agent';
import type { Conversation } from '@/types/models/conversation';

const numberFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });

/**
 * The id of the conversation shown on the page, which owns the tool calls of the artifacts.
 */
export function useConversationId(): string {
    return usePage<{ conversation: Conversation }>().props.conversation.id;
}

export function formatNumber(value: number): string {
    return numberFormatter.format(value);
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
    const units: [string, number][] = [['j', 86400], ['h', 3600], ['min', 60], ['s', 1]];

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
        return numberFormatter.format(amount);
    }

    try {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(amount);
    } catch {
        return `${numberFormatter.format(amount)} ${currency}`;
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
            return `${numberFormatter.format(number)} %`;
        case 'duration':
            return formatDuration(number);
        default:
            return numberFormatter.format(number);
    }
}
