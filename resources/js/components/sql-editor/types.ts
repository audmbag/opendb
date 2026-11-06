/**
 * A .sql file of the editor, as listed: its content is read only when its tab opens.
 */
export type SqlFileSummary = {
    name: string;
    size: number;
    modifiedAt: number;
};

export type SqlFile = {
    name: string;
    sql: string;
    version: string;
};

/**
 * Where the content of a tab stands against its file.
 */
export type SaveStatus =
    | 'loading'
    | 'saved'
    | 'pending'
    | 'saving'
    | 'conflict'
    | 'error';
