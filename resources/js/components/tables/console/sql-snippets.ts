import { completeFromList, snippetCompletion } from '@codemirror/autocomplete';
import type { Completion, CompletionSource } from '@codemirror/autocomplete';
import { trans } from '@/lib/i18n';

export type Snippet = { label: string; detail: string; template: string };

/**
 * The snippets of a project, kept in its `.opendb/snippets.json`: those the user wrote, and the names of those of
 * opendb hidden. Invalid when the file cannot be read: it is then never written over.
 */
export type SnippetConfig = {
    snippets: Snippet[];
    hidden: string[];
    invalid: boolean;
};

/**
 * The models written in every engine. `${name}` marks a field: Tab goes to the next one. The detail of a model of
 * opendb is a key of lang/{locale}/tables.php, the one of a model of the user its own text: snippetDetail() shows both.
 */
const COMMON: Snippet[] = [
    {
        label: 'sel',
        detail: 'tables.snippets.select',
        template: 'SELECT ${*} FROM ${table} WHERE ${condition} LIMIT 100;',
    },
    {
        label: 'cte',
        detail: 'tables.snippets.cte',
        template:
            'WITH ${name} AS (\n\tSELECT ${*} FROM ${table}\n)\nSELECT * FROM ${name};',
    },
    {
        label: 'countby',
        detail: 'tables.snippets.count_by',
        template:
            'SELECT ${column}, COUNT(*) AS total\nFROM ${table}\nGROUP BY ${column}\nORDER BY total DESC;',
    },
    {
        label: 'duplicates',
        detail: 'tables.snippets.duplicates',
        template:
            'SELECT ${column}, COUNT(*) AS total\nFROM ${table}\nGROUP BY ${column}\nHAVING COUNT(*) > 1;',
    },
    {
        label: 'rownumber',
        detail: 'tables.snippets.row_number',
        template:
            'ROW_NUMBER() OVER (PARTITION BY ${group} ORDER BY ${column} DESC)',
    },
    {
        label: 'runningtotal',
        detail: 'tables.snippets.running_total',
        template: 'SUM(${value}) OVER (ORDER BY ${column})',
    },
    {
        label: 'lag',
        detail: 'tables.snippets.lag',
        template: 'LAG(${value}) OVER (ORDER BY ${column})',
    },
    {
        label: 'case',
        detail: 'tables.snippets.case',
        template: 'CASE WHEN ${condition} THEN ${value} ELSE ${other} END',
    },
    {
        label: 'insert',
        detail: 'tables.snippets.insert',
        template: 'INSERT INTO ${table} (${columns}) VALUES (${values});',
    },
    {
        label: 'update',
        detail: 'tables.snippets.update',
        template:
            'UPDATE ${table} SET ${column} = ${value} WHERE ${condition};',
    },
    {
        label: 'delete',
        detail: 'tables.snippets.delete',
        template: 'DELETE FROM ${table} WHERE ${condition};',
    },
];

const MYSQL: Snippet[] = [
    {
        label: 'upsert',
        detail: 'tables.snippets.upsert_duplicate',
        template:
            'INSERT INTO ${table} (${key}, ${column}) VALUES (${keyValue}, ${value})\nON DUPLICATE KEY UPDATE ${column} = VALUES(${column});',
    },
    {
        label: 'bymonth',
        detail: 'tables.snippets.by_month',
        template:
            "SELECT DATE_FORMAT(${date}, '%Y-%m') AS month, COUNT(*) AS total\nFROM ${table}\nGROUP BY month\nORDER BY month;",
    },
    {
        label: 'jsonvalue',
        detail: 'tables.snippets.json_value',
        template: "${column}->>'$.${key}'",
    },
];

const POSTGRES: Snippet[] = [
    {
        label: 'upsert',
        detail: 'tables.snippets.upsert_conflict',
        template:
            'INSERT INTO ${table} (${key}, ${column}) VALUES (${keyValue}, ${value})\nON CONFLICT (${key}) DO UPDATE SET ${column} = EXCLUDED.${column};',
    },
    {
        label: 'bymonth',
        detail: 'tables.snippets.by_month',
        template:
            "SELECT date_trunc('month', ${date}) AS month, COUNT(*) AS total\nFROM ${table}\nGROUP BY month\nORDER BY month;",
    },
    {
        label: 'jsonvalue',
        detail: 'tables.snippets.json_value',
        template: "${column} ->> '${key}'",
    },
    {
        label: 'returning',
        detail: 'tables.snippets.returning',
        template:
            'UPDATE ${table} SET ${column} = ${value} WHERE ${condition}\nRETURNING *;',
    },
];

const SQLITE: Snippet[] = [
    {
        label: 'upsert',
        detail: 'tables.snippets.upsert_conflict',
        template:
            'INSERT INTO ${table} (${key}, ${column}) VALUES (${keyValue}, ${value})\nON CONFLICT (${key}) DO UPDATE SET ${column} = excluded.${column};',
    },
    {
        label: 'bymonth',
        detail: 'tables.snippets.by_month',
        template:
            "SELECT strftime('%Y-%m', ${date}) AS month, COUNT(*) AS total\nFROM ${table}\nGROUP BY month\nORDER BY month;",
    },
    {
        label: 'jsonvalue',
        detail: 'tables.snippets.json_value',
        template: "json_extract(${column}, '$.${key}')",
    },
];

const BY_DRIVER: Record<string, Snippet[]> = {
    mysql: MYSQL,
    mariadb: MYSQL,
    pgsql: POSTGRES,
    sqlite: SQLITE,
};

/**
 * The snippets of opendb for the engine of the project, the common ones first.
 */
export function builtinSnippets(driver: string): Snippet[] {
    return [...COMMON, ...(BY_DRIVER[driver] ?? [])];
}

/**
 * The snippets offered in the editor: those of the user, then those of opendb they did not hide. A snippet of the user
 * replaces the one of opendb of the same name, like a copy they changed.
 */
export function activeSnippets(
    driver: string,
    config?: SnippetConfig,
): Snippet[] {
    const own = config?.snippets ?? [];
    const taken = new Set(own.map((snippet) => snippet.label.toLowerCase()));
    const hidden = new Set(config?.hidden ?? []);

    return [
        ...own,
        ...builtinSnippets(driver).filter(
            (snippet) =>
                !hidden.has(snippet.label) &&
                !taken.has(snippet.label.toLowerCase()),
        ),
    ];
}

/**
 * The description of a snippet in the language of the interface: translated for the models of opendb, as written for
 * those of the user.
 */
export const snippetDetail = (detail: string): string => trans(detail);

/**
 * The text of a snippet with each field replaced by its name, as written in a new file where no field can be filled.
 */
export const plainTemplate = (template: string): string =>
    template.replace(/\$\{([^}]*)\}/g, '$1');

/**
 * The snippets offered by the completion. They come after the tables, columns and keywords, which are what is usually
 * typed.
 */
export function snippetSource(snippets: Snippet[]): CompletionSource {
    const completions: Completion[] = snippets.map(
        ({ label, detail, template }) =>
            snippetCompletion(template, {
                label,
                detail: snippetDetail(detail),
                type: 'text',
                section: trans('sql_editor.explorer.snippets'),
                boost: -10,
            }),
    );

    return completeFromList(completions);
}
