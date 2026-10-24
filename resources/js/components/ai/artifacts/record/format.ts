import { formatCell } from '@/components/ai/artifacts/format';
import type { CellValue, InspectedRecord, RecordKey } from '@/types/agent';

export type RecordLocation = { table: string; key: RecordKey };

export type OutgoingRelation = InspectedRecord['outgoing'][number];

export type IncomingRelation = InspectedRecord['incoming'][number];

export type ValueDisplay = 'null' | 'boolean' | 'number' | 'json' | 'temporal' | 'text';

const NUMBER_PATTERN = /^-?\d+(?:\.\d+)?$/;

const TEMPORAL_PATTERN = /^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/;

const relativeTimeFormatter = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });

const relativeTimeUnits: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
    ['second', 1],
];

/**
 * « id = 42 », or « tenant_id = 3 · id = 42 » for a composite key.
 */
export function describeKey(key: RecordKey): string {
    return Object.entries(key)
        .map(([column, value]) => `${column} = ${formatCell(value)}`)
        .join(' · ');
}

/**
 * « orders #42 », the label of a record in the breadcrumb.
 */
export function describeLocation({ table, key }: RecordLocation): string {
    return `${table} #${Object.values(key).map(formatCell).join(' · ')}`;
}

/**
 * The object or array of a JSON value, null when the string is not JSON.
 */
export function parseJson(value: string): object | null {
    const trimmed = value.trim();

    if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) {
        return null;
    }

    try {
        const parsed: unknown = JSON.parse(trimmed);

        return typeof parsed === 'object' && parsed !== null ? parsed : null;
    } catch {
        return null;
    }
}

/**
 * How a value is displayed, guessed from the value itself since the record does not carry the types of its columns.
 */
export function valueDisplay(value: CellValue): ValueDisplay {
    if (value === null) {
        return 'null';
    }

    if (typeof value === 'boolean') {
        return 'boolean';
    }

    if (typeof value === 'number' || NUMBER_PATTERN.test(value)) {
        return 'number';
    }

    if (TEMPORAL_PATTERN.test(value)) {
        return 'temporal';
    }

    return parseJson(value) ? 'json' : 'text';
}

/**
 * « il y a 4 jours », « dans 2 heures », null when the date cannot be read.
 */
export function formatRelativeTime(value: string): string | null {
    const time = Date.parse(value.includes('T') ? value : value.replace(' ', 'T'));

    if (Number.isNaN(time)) {
        return null;
    }

    const seconds = (time - Date.now()) / 1000;
    const [unit, size] = relativeTimeUnits.find(([, size]) => Math.abs(seconds) >= size) ?? ['second', 1];

    return relativeTimeFormatter.format(Math.round(seconds / size), unit);
}

/**
 * « 4 enregistrements dans 2 tables pointent vers celui-ci. »
 */
export function describeIncoming(relations: IncomingRelation[]): string {
    const total = relations.reduce((sum, relation) => sum + relation.count, 0);
    const tables = new Set(relations.filter((relation) => relation.count > 0).map((relation) => relation.table)).size;

    if (total === 0) {
        return 'Aucun enregistrement ne pointe vers celui-ci.';
    }

    const records = total > 1 ? `${formatCell(total)} enregistrements` : '1 enregistrement';
    const verb = total > 1 ? 'pointent' : 'pointe';

    return `${records} dans ${tables > 1 ? `${tables} tables` : '1 table'} ${verb} vers celui-ci.`;
}
