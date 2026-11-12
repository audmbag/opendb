import { useLaravelReactI18n } from 'laravel-react-i18n';
import { PlusIcon, XIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import Button from '@/components/form/button';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { Filter, FilterMatch, FilterOperator, TableColumn } from './types';
import {
    defaultOperator,
    newFilterId,
    OPERATORS,
    operatorNeedsValue,
} from './view';

type Props = {
    columns: TableColumn[];
    filters: Filter[];
    match: FilterMatch;
    explanation: string | null;
    onChange: (filters: Filter[], match: FilterMatch) => void;
    onDismissExplanation: () => void;
};

/**
 * Typing in a value waits for a pause before reading the rows again, so a long word is not a request per letter.
 */
const TYPING_DELAY = 350;

const TEXTUAL_KINDS = [
    'string',
    'text',
    'json',
    'uuid',
    'enum',
    'date',
    'datetime',
    'time',
];

function FilterRow({
    filter,
    columns,
    onChange,
    onRemove,
    autoFocus,
}: {
    filter: Filter;
    columns: TableColumn[];
    onChange: (filter: Filter) => void;
    onRemove: () => void;
    autoFocus: boolean;
}) {
    const { t } = useLaravelReactI18n();
    const column = columns.find((item) => item.name === filter.column);
    const [value, setValue] = useState(filter.value ?? '');
    const [syncedValue, setSyncedValue] = useState(filter.value);

    // The filter was replaced from outside, by the assistant or a right click: its value follows.
    if (filter.value !== syncedValue) {
        setSyncedValue(filter.value);
        setValue(filter.value ?? '');
    }

    useEffect(() => {
        if (value === (filter.value ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () => onChange({ ...filter, value }),
            TYPING_DELAY,
        );

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    const operators = OPERATORS.filter(
        (operator) =>
            !operator.textual ||
            TEXTUAL_KINDS.includes(column?.kind ?? 'string'),
    );
    const placeholder =
        filter.operator === 'in'
            ? t('tables.filters.placeholders.in')
            : column?.kind === 'date'
              ? t('tables.filters.placeholders.date')
              : column?.kind === 'boolean'
                ? t('tables.filters.placeholders.boolean')
                : t('common.value');

    return (
        <li className="flex flex-wrap items-center gap-2">
            <NativeSelect
                size="sm"
                aria-label={t('ai.profile.headers.column')}
                value={filter.column}
                onChange={(event) => {
                    const next = columns.find(
                        (item) => item.name === event.target.value,
                    );
                    const keepsOperator =
                        OPERATORS.find(
                            (operator) => operator.value === filter.operator,
                        )?.textual !== true ||
                        TEXTUAL_KINDS.includes(next?.kind ?? 'string');

                    onChange({
                        ...filter,
                        column: event.target.value,
                        operator: keepsOperator
                            ? filter.operator
                            : defaultOperator(next?.kind),
                    });
                }}
                className="w-44"
            >
                {columns.map((item) => (
                    <NativeSelectOption key={item.name} value={item.name}>
                        {item.name}
                    </NativeSelectOption>
                ))}
            </NativeSelect>

            <NativeSelect
                size="sm"
                aria-label={t('tables.filters.comparison')}
                value={filter.operator}
                onChange={(event) => {
                    const operator = event.target.value as FilterOperator;

                    onChange({
                        ...filter,
                        operator,
                        value: operatorNeedsValue(operator) ? value : null,
                    });
                }}
                className="w-48"
            >
                {operators.map((operator) => (
                    <NativeSelectOption
                        key={operator.value}
                        value={operator.value}
                    >
                        {t(operator.label)}
                    </NativeSelectOption>
                ))}
            </NativeSelect>

            {operatorNeedsValue(filter.operator) && (
                <input
                    autoFocus={autoFocus}
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            onChange({ ...filter, value });
                        }
                    }}
                    placeholder={placeholder}
                    aria-label={t('common.value')}
                    className="h-8 w-56 min-w-0 rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                />
            )}

            <Button
                variant="ghost"
                size="icon-sm"
                onClick={onRemove}
                aria-label={t('tables.filters.remove')}
            >
                <XIcon />
            </Button>
        </li>
    );
}

/**
 * A new filter, on the first column worth filtering, waiting for its value.
 */
export function createFilter(
    columns: TableColumn[],
    column?: TableColumn,
): Filter {
    const target =
        column ??
        columns.find((item) => !item.primary && item.kind !== 'binary') ??
        columns[0];

    return {
        id: newFilterId(),
        column: target.name,
        operator: defaultOperator(target.kind),
        value: '',
    };
}

/**
 * The filters of the view, one per line. They are applied as soon as they are complete: a filter whose value is
 * still empty waits for it instead of matching nothing.
 */
export default function FilterBar({
    columns,
    filters,
    match,
    explanation,
    onChange,
    onDismissExplanation,
}: Props) {
    const { t } = useLaravelReactI18n();
    const add = () => onChange([...filters, createFilter(columns)], match);

    if (filters.length === 0 && !explanation) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 border-b px-3 py-2.5">
            {explanation && (
                <p className="flex items-start gap-2 text-sm text-muted-foreground">
                    <span className="min-w-0 flex-1">{explanation}</span>
                    <button
                        type="button"
                        onClick={onDismissExplanation}
                        className="shrink-0 text-xs underline-offset-4 hover:text-foreground hover:underline"
                    >
                        {t('tables.filters.hide')}
                    </button>
                </p>
            )}

            {filters.length > 1 && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    {t('tables.filters.rows_matching')}
                    <NativeSelect
                        size="sm"
                        aria-label={t('tables.filters.combination')}
                        value={match}
                        onChange={(event) =>
                            onChange(filters, event.target.value as FilterMatch)
                        }
                    >
                        <NativeSelectOption value="all">
                            {t('tables.filters.all')}
                        </NativeSelectOption>
                        <NativeSelectOption value="any">
                            {t('tables.filters.any')}
                        </NativeSelectOption>
                    </NativeSelect>
                </div>
            )}

            <ul
                className={cn(
                    'flex flex-col gap-2',
                    filters.length === 0 && 'hidden',
                )}
            >
                {filters.map((filter) => (
                    <FilterRow
                        key={filter.id}
                        filter={filter}
                        columns={columns}
                        autoFocus={
                            filter.value === '' &&
                            operatorNeedsValue(filter.operator)
                        }
                        onChange={(next) =>
                            onChange(
                                filters.map((item) =>
                                    item.id === next.id ? next : item,
                                ),
                                match,
                            )
                        }
                        onRemove={() =>
                            onChange(
                                filters.filter((item) => item.id !== filter.id),
                                match,
                            )
                        }
                    />
                ))}
            </ul>

            <div className="flex items-center gap-2">
                <Button variant="ghost" size="sm" onClick={add}>
                    <PlusIcon />
                    {t('tables.filters.add')}
                </Button>
                {filters.length > 0 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-muted-foreground"
                        onClick={() => onChange([], 'all')}
                    >
                        {t('tables.filters.remove_all')}
                    </Button>
                )}
            </div>
        </div>
    );
}
