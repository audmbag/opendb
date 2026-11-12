import type { Edge, Node } from '@xyflow/react';
import type { ReactNode } from 'react';

/**
 * What a draft of the assistant does to a table: it creates it, changes it or drops it.
 */
export type DraftTableState = 'new' | 'changed' | 'dropped';

/**
 * What a draft of the assistant does to a column of a table it changes.
 */
export type DraftColumnState = 'added' | 'changed' | 'dropped';

export interface SchemaColumn {
    name: string;
    type: string;
    pk?: boolean;
    fk?: boolean;
    draft?: DraftColumnState | null;
}

export interface SchemaTable {
    id: string;
    name: string;
    color?: string;
    columns: SchemaColumn[];
    draft?: DraftTableState;
}

export interface SchemaRelationship {
    source: string;
    sourceColumn: string;
    target: string;
    targetColumn: string;
    /**
     * The foreign key is the primary key or a unique index of its table: each referenced row has one row at most.
     */
    unique?: boolean;
    /**
     * A row may reference nothing.
     */
    nullable?: boolean;
    /**
     * A foreign key a draft of the assistant adds.
     */
    draft?: boolean;
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
    draft?: DraftColumnState | null;
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
    draft?: boolean;
}

export interface SchemaExportTable {
    name: string;
    columns: SchemaExportColumn[];
    primary_key: string[];
    indexes: SchemaExportIndex[];
    foreign_keys: SchemaExportForeignKey[];
    draft?: DraftTableState;
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
    /**
     * The column a `*_id` column without constraint likely references (TRUSS-INT-002).
     */
    references?: { table: string; column: string } | null;
}

export interface SchemaDiagnostics {
    summary: { total: number; error: number; warning: number; info: number };
    findings: SchemaDoctorFinding[];
}

/**
 * The number of findings of a table for each severity.
 */
export type FindingCounts = Record<SchemaDoctorFinding['severity'], number>;

/**
 * One end of a relationship drawn by the user between two columns.
 */
export type ColumnEnd = { table: string; column: string };

export type SchemaExport = SchemaExportTable[];

export type Point = { x: number; y: number };

export type HandleSide = 'left' | 'right';

/**
 * How much of each table the diagram shows: every column, only the keys, or the name of the table alone.
 */
export type SchemaDetail = 'all' | 'keys' | 'names';

/**
 * What gives each table its color: the table itself, the prefix of its name, or the group of tables linked to it.
 */
export type SchemaColorBy = 'table' | 'prefix' | 'component';

/**
 * The handle of a table shown without its columns, where all its relationships start and end.
 */
export const TABLE_HANDLE = '#table';

export const tableHandleId = (
    column: string,
    kind: 'source' | 'target',
    side: HandleSide,
): string => `${column}#${kind}#${side}`;

/**
 * The column of a handle id written by tableHandleId. A column name may itself contain a `#`.
 */
export const handleColumn = (handleId: string): string =>
    handleId.split('#').slice(0, -2).join('#');

export type TableNodeData = {
    name: string;
    columns: SchemaColumn[];
    color: string;
    highlightedColumns?: string[];
    isActive?: boolean;
    findings?: FindingCounts;
    /**
     * The user can draw a foreign key from a column: its handle shows when the row is hovered.
     */
    connectable?: boolean;
    draft?: DraftTableState;
};

export type TableNodeType = Node<TableNodeData, 'table'>;

export type RelationshipEdgeData = {
    active?: boolean;
    bendPoints?: Point[];
    sourceColumn?: string;
    showForeignKeys?: boolean;
    /**
     * The notation of the number of rows on each side, drawn in place of the squares at both ends.
     */
    cardinality?: { unique: boolean; nullable: boolean };
    /**
     * A relationship guessed from the name of a column without constraint.
     */
    implicit?: boolean;
    /**
     * A foreign key a draft of the assistant adds.
     */
    draft?: boolean;
};

export type RelationshipEdgeType = Edge<RelationshipEdgeData, 'relationship'>;

export interface SchemaCanvasProps {
    schema: SchemaExport | null;
    showForeignKeys?: boolean;
    /**
     * Every column by default, as in the artifacts of the conversation.
     */
    detail?: SchemaDetail;
    /**
     * The table to bring into the view, with the other tables framed with it. A new request frames them again.
     */
    centeredTable?: {
        name: string;
        others?: string[];
        requestId: number;
    } | null;
    /**
     * The selected table, when the page decides it. Without it, the diagram keeps its own selection.
     */
    selectedTable?: string | null;
    /**
     * Called with the table the user selects, or null once nothing is selected.
     */
    onTableSelect?: (table: string | null) => void;
    /**
     * A panel shown beside the diagram, also in the full screen view.
     */
    aside?: ReactNode;
    /**
     * The wheel zooms and the view stays where the user moved it when the canvas is resized, for a diagram that fills
     * its page.
     */
    zoomOnScroll?: boolean;
    /**
     * The places of the tables moved by the user, kept over the automatic layout. Tables without one are laid out
     * below them.
     */
    savedPositions?: Record<string, Point>;
    /**
     * Called with the place of every table drawn once the user moved one.
     */
    onPositionsChange?: (positions: Record<string, Point>) => void;
    showCardinality?: boolean;
    colorBy?: SchemaColorBy;
    minimap?: boolean;
    /**
     * The findings of the diagnostic of each table, shown as a badge in its header.
     */
    findingsByTable?: Record<string, FindingCounts>;
    /**
     * Relationships without constraint, drawn dashed.
     */
    implicitRelationships?: SchemaRelationship[];
    /**
     * Called when the user draws a link from a column to the column it should reference. Without it, nothing can be
     * linked.
     */
    onConnectColumns?: (source: ColumnEnd, target: ColumnEnd) => void;
    /**
     * The tables laid out before keep their place when the tables drawn change, as when the assistant adds one:
     * only the new ones are placed, below the others.
     */
    keepLayout?: boolean;
}
