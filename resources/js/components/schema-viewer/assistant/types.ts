import type { SchemaExportTable } from '../types';

/**
 * A table of a draft of the assistant, as it will be once the changes run.
 */
export type DraftTable = SchemaExportTable & {
    /**
     * The name the table has in the database, when the draft renames it.
     */
    replaces: string | null;
};

/**
 * The tables the changes of the assistant would create, change and drop.
 */
export type SchemaDraft = {
    tables: DraftTable[];
    dropped: string[];
};

export type SchemaChangeKind =
    | 'create_table'
    | 'alter_table'
    | 'drop_table'
    | 'raw_sql';

/**
 * The changes asked by the assistant read against the database by the server, before they run.
 */
export type SchemaChangePreview = {
    valid: boolean;
    destructive: boolean;
    changes: {
        kind: SchemaChangeKind;
        table: string | null;
        from: string | null;
        destructive: boolean;
        errors: string[];
    }[];
    errors: string[];
    statements: string[];
    failure: { message: string; statement: string } | null;
    draft: SchemaDraft;
};

/**
 * Changes of the assistant waiting for the user to apply or reject them.
 */
export type SchemaProposal = {
    toolCallId: string;
    approvalId: string;
    changes: Record<string, unknown>[];
};

/**
 * What the tool answers once the changes ran, or why they did not.
 */
export type SchemaChangeResult = {
    success: boolean;
    applied?: { kind: SchemaChangeKind; table: string | null }[];
    statements?: string[];
    error?: string;
    errors?: string[];
    statement?: string;
};
