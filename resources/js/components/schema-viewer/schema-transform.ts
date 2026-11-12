import type { DbSchema, SchemaExport, SchemaTable } from './types';

export function schemaExportToDbSchema(schema: SchemaExport): DbSchema {
    const tables: SchemaTable[] = schema.map((table) => ({
        id: table.name,
        name: table.name,
        draft: table.draft,
        columns: table.columns.map((column) => ({
            name: column.name,
            type: column.type,
            draft: column.draft,
            pk: table.primary_key.includes(column.name),
            fk: table.foreign_keys.some((foreignKey) =>
                foreignKey.columns.includes(column.name),
            ),
        })),
    }));

    const tableNames = new Set(tables.map((table) => table.id));
    const relationships = schema.flatMap((table) =>
        table.foreign_keys.flatMap((foreignKey) => {
            if (!tableNames.has(foreignKey.references_table)) {
                return [];
            }

            const sameColumns = (columns: string[]) =>
                columns.length === foreignKey.columns.length &&
                columns.every((column) => foreignKey.columns.includes(column));
            const unique =
                sameColumns(table.primary_key) ||
                table.indexes.some(
                    (index) => index.unique && sameColumns(index.columns),
                );
            const nullable = table.columns.some(
                (column) =>
                    column.nullable && foreignKey.columns.includes(column.name),
            );

            return foreignKey.columns.map((column, index) => ({
                unique,
                nullable,
                draft: foreignKey.draft,
                source: table.name,
                sourceColumn: column,
                target: foreignKey.references_table,
                targetColumn:
                    foreignKey.references_columns[index] ??
                    foreignKey.references_columns[0],
            }));
        }),
    );

    return { tables, relationships };
}
