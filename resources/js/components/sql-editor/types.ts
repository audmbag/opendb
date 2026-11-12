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
