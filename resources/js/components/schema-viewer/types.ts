import type { Edge, Node } from '@xyflow/react';

export interface SchemaColumn {
    name: string;
    type: string;
    pk?: boolean;
    fk?: boolean;
}

export interface SchemaTable {
    id: string;
    name: string;
    color?: string;
    columns: SchemaColumn[];
}

export interface SchemaRelationship {
    source: string;
    sourceColumn: string;
    target: string;
    targetColumn: string;
}

export interface DbSchema {
    tables: SchemaTable[];
    relationships: SchemaRelationship[];
}

export interface SchemaExportColumn {
    name: string;
    type: string;
    nullable: boolean;
    default: string | number | null;
}

export interface SchemaExportIndex {
    name: string;
    columns: string[];
    unique: boolean;
}

export interface SchemaExportForeignKey {
    name: string;
    columns: string[];
    references_table: string;
    references_columns: string[];
    on_update: string;
    on_delete: string;
}

export interface SchemaExportTable {
    name: string;
    columns: SchemaExportColumn[];
    primary_key: string[];
    indexes: SchemaExportIndex[];
    foreign_keys: SchemaExportForeignKey[];
}

export interface SchemaDoctorFinding {
    code: string;
    severity: 'error' | 'warning' | 'info';
    connection: string;
    table: string;
    column: string;
    message: string;
    hint: string | null;
    suggestion: string | null;
    confidence: string;
    category: string;
    fingerprint: string;
}

export type SchemaExport = SchemaExportTable[]

export type Point = { x: number; y: number };

export type HandleSide = 'left' | 'right';

export const tableHandleId = (
    column: string,
    kind: 'source' | 'target',
    side: HandleSide,
): string => `${column}#${kind}#${side}`;

export type TableNodeData = {
    name: string;
    columns: SchemaColumn[];
    color: string;
    highlightedColumns?: string[];
    isActive?: boolean;
};

export type TableNodeType = Node<TableNodeData, 'table'>;

export type RelationshipEdgeData = {
    active?: boolean;
    bendPoints?: Point[];
    sourceColumn?: string;
    showForeignKeys?: boolean;
};

export type RelationshipEdgeType = Edge<RelationshipEdgeData, 'relationship'>;

export interface SchemaCanvasProps {
    schema: SchemaExport | null;
    showForeignKeys?: boolean;
}
