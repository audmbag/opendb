import { trans } from '@/lib/i18n';
import type { QueryParams } from '@/wayfinder';
import type {
    ColumnKind,
    Filter,
    FilterMatch,
    FilterOperator,
    Sort,
    TableView,
} from './types';

export const PAGE_SIZES = [50, 100, 250, 500] as const;

export const DEFAULT_VIEW: TableView = {
    filters: [],
    match: 'all',
    sorts: [],
    page: 1,
    perPage: 100,
};

export const OPERATORS: {
    value: FilterOperator;
    label: string;
    needsValue: boolean;
    textual?: boolean;
}[] = [
    { value: '=', label: 'tables.filters.operators.equals', needsValue: true },
    { value: '!=', label: 'tables.filters.operators.not_equals', needsValue: true },
    { value: '>', label: 'tables.filters.operators.greater', needsValue: true },
    { value: '>=', label: 'tables.filters.operators.greater_or_equal', needsValue: true },
    { value: '<', label: 'tables.filters.operators.less', needsValue: true },
    { value: '<=', label: 'tables.filters.operators.less_or_equal', needsValue: true },
    { value: 'contains', label: 'tables.filters.operators.contains', needsValue: true, textual: true },
    {
        value: 'not_contains',
        label: 'tables.filters.operators.not_contains',
        needsValue: true,
        textual: true,
    },
    {
        value: 'starts_with',
        label: 'tables.filters.operators.starts_with',
        needsValue: true,
        textual: true,
    },
    { value: 'ends_with', label: 'tables.filters.operators.ends_with', needsValue: true, textual: true },
    { value: 'in', label: 'tables.filters.operators.in', needsValue: true },
    { value: 'is_null', label: 'tables.filters.operators.is_null', needsValue: false },
    { value: 'is_not_null', label: 'tables.filters.operators.is_not_null', needsValue: false },
];

/**
 * The label of an operator in the language of the interface, its key being in lang/{locale}/tables.php.
 */
export const operatorLabel = (operator: FilterOperator): string => {
    const label = OPERATORS.find((item) => item.value === operator)?.label;

    return label ? trans(label) : operator;
};

export const operatorNeedsValue = (operator: FilterOperator): boolean =>
    OPERATORS.find((item) => item.value === operator)?.needsValue ?? true;

/**
 * The operator a new filter on a column starts with: a text search for text, an equality otherwise.
 */
export const defaultOperator = (
    kind: ColumnKind | undefined,
): FilterOperator => (kind === 'string' || kind === 'text' ? 'contains' : '=');

export const newFilterId = (): string =>
    Math.random().toString(36).slice(2, 10);

/**
 * The filters that can be sent: a filter waiting for its value is left out until the user types it.
 */
export const completeFilters = (filters: Filter[]): Filter[] =>
    filters.filter(
        (filter) =>
            !operatorNeedsValue(filter.operator) ||
            (filter.value !== null && filter.value !== ''),
    );

/**
 * The view as query parameters, for the endpoints and the address of the page. Defaults are left out to keep it short.
 */
export function viewQuery(
    view: TableView,
    {
        withPage = true,
        withSorts = true,
    }: { withPage?: boolean; withSorts?: boolean } = {},
): QueryParams {
    const filters = completeFilters(view.filters);

    return {
        filters: filters.length
            ? Object.fromEntries(
                  filters.map(({ column, operator, value }, index) => [
                      index,
                      {
                          column,
                          operator,
                          value: operatorNeedsValue(operator)
                              ? value
                              : undefined,
                      },
                  ]),
              )
            : undefined,
        match: filters.length > 1 && view.match === 'any' ? 'any' : undefined,
        sorts:
            withSorts && view.sorts.length
                ? Object.fromEntries(
                      view.sorts.map((sort, index) => [index, sort]),
                  )
                : undefined,
        page: withPage && view.page > 1 ? view.page : undefined,
        perPage:
            withPage && view.perPage !== DEFAULT_VIEW.perPage
                ? view.perPage
                : undefined,
    };
}

/**
 * The view written in the address of the page, so a reload or a shared link opens the same rows.
 */
export function parseViewQuery(search: string): TableView {
    const params = new URLSearchParams(search);
    const indexed = (
        prefix: string,
    ): Record<string, Record<string, string>> => {
        const groups: Record<string, Record<string, string>> = {};

        params.forEach((value, key) => {
            const match = new RegExp(
                `^${prefix}\\[(\\d+)\\]\\[(\\w+)\\]$`,
            ).exec(key);

            if (match) {
                groups[match[1]] = { ...groups[match[1]], [match[2]]: value };
            }
        });

        return groups;
    };

    const operators = OPERATORS.map((operator) => operator.value);
    const filters: Filter[] = Object.values(indexed('filters'))
        .filter(
            (filter) =>
                filter.column &&
                operators.includes(filter.operator as FilterOperator),
        )
        .map((filter) => ({
            id: newFilterId(),
            column: filter.column,
            operator: filter.operator as FilterOperator,
            value: filter.value ?? null,
        }));

    const sorts: Sort[] = Object.values(indexed('sorts'))
        .filter((sort) => sort.column)
        .map((sort) => ({
            column: sort.column,
            direction: sort.direction === 'desc' ? 'desc' : 'asc',
        }));

    const perPage = Number(params.get('perPage'));
    const page = Number(params.get('page'));

    return {
        filters,
        match: (params.get('match') as FilterMatch) === 'any' ? 'any' : 'all',
        sorts,
        page: Number.isInteger(page) && page > 1 ? page : 1,
        perPage: (PAGE_SIZES as readonly number[]).includes(perPage)
            ? perPage
            : DEFAULT_VIEW.perPage,
    };
}

/**
 * The next sorts after a click on a column header: ascending, descending, then not sorted.
 * With Shift, the column is added to the current sorts instead of replacing them.
 */
export function nextSorts(
    sorts: Sort[],
    column: string,
    additive: boolean,
): Sort[] {
    const current = sorts.find((sort) => sort.column === column);
    const next: Sort | null = !current
        ? { column, direction: 'asc' }
        : current.direction === 'asc'
          ? { column, direction: 'desc' }
          : null;

    if (!additive) {
        return next ? [next] : [];
    }

    const others = sorts.filter((sort) => sort.column !== column);

    return next
        ? current
            ? sorts.map((sort) => (sort.column === column ? next : sort))
            : [...others, next]
        : others;
}
