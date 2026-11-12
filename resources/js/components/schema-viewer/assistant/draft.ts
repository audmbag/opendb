import type { SchemaExport } from '../types';
import type { SchemaDraft } from './types';

/**
 * The schema with a draft of the assistant drawn over it: each table it changes replaced by the table as it will be,
 * the tables it drops marked, and the tables it creates added after the others, unless they already exist.
 */
export function schemaWithDraft(
    schema: SchemaExport,
    draft: SchemaDraft | null,
): SchemaExport {
    if (!draft) {
        return schema;
    }

    const changed = new Map(
        draft.tables
            .filter((table) => table.draft === 'changed')
            .map((table) => [table.replaces ?? table.name, table]),
    );
    const dropped = new Set(draft.dropped);
    // Once the changes ran, the schema read again holds the new tables before the draft is cleared.
    const existing = new Set(schema.map((table) => table.name));

    return [
        ...schema.map((table) => {
            const next = changed.get(table.name);

            if (next) {
                return next;
            }

            return dropped.has(table.name)
                ? { ...table, draft: 'dropped' as const }
                : table;
        }),
        ...draft.tables.filter(
            (table) => table.draft === 'new' && !existing.has(table.name),
        ),
    ];
}
