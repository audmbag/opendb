import type { DbSchema, SchemaExport, SchemaTable } from './types';

export function schemaExportToDbSchema(schema: SchemaExport): DbSchema {
    const tables: SchemaTable[] = schema.map((table) => ({
        id: table.name,
        name: table.name,
        columns: table.columns.map((column) => ({
            name: column.name,
            type: column.type,
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

            return foreignKey.columns.map((column, index) => ({
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
