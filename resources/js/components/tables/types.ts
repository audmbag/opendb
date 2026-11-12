import type { CellValue, RecordKey } from '@/types/agent';

export type TableSummary = {
    name: string;
    columns: number;
    hasPrimaryKey: boolean;
    foreignKeys: number;
    agentAccessEnabled: boolean;
};

export type ColumnKind =
    | 'string'
    | 'text'
    | 'integer'
    | 'decimal'
    | 'boolean'
    | 'date'
    | 'datetime'
    | 'time'
    | 'json'
    | 'uuid'
    | 'enum'
    | 'binary';

export type TableColumn = {
    name: string;
    kind: ColumnKind;
    db_type: string;
    nullable: boolean;
    default: string | null;
    primary: boolean;
    auto_increment: boolean;
    generated: boolean;
    editable: boolean;
    max_length: number | null;
    options: string[] | null;
    unique: boolean;
    references: { table: string; column: string } | null;
};

export type TableIndex = { name: string; columns: string[]; unique: boolean };

export type TableForeignKey = {
    name: string;
    columns: string[];
    references_table: string;
    references_columns: string[];
    on_update: string | null;
    on_delete: string | null;
};

export type TableStructure = {
    name: string;
    primaryKey: string[];
    columns: TableColumn[];
    indexes: TableIndex[];
    foreignKeys: TableForeignKey[];
    incomingReferences: {
        table: string;
        columns: string[];
        references_columns: string[];
    }[];
};

export type Row = Record<string, CellValue>;

export type RowsPage = {
    rows: Row[];
    page: number;
    perPage: number;
    hasMore: boolean;
    /**
     * The statement that read the page, with its values written in.
     */
    sql: string;
};

export type RowCount = { total: number; estimated: boolean };

export type FilterOperator =
    | '='
    | '!='
    | '>'
    | '>='
    | '<'
    | '<='
    | 'contains'
    | 'not_contains'
    | 'starts_with'
    | 'ends_with'
    | 'in'
    | 'is_null'
    | 'is_not_null';

export type Filter = {
    id: string;
    column: string;
    operator: FilterOperator;
    value: string | null;
};

export type Sort = { column: string; direction: 'asc' | 'desc' };

export type FilterMatch = 'all' | 'any';

export type TableView = {
    filters: Filter[];
    match: FilterMatch;
    sorts: Sort[];
    page: number;
    perPage: number;
};

export type ChangeType = 'insert' | 'update' | 'delete';

export type TableChange = {
    type: ChangeType;
    key?: RecordKey;
    values?: Record<string, CellValue>;
};

export type FilterSuggestion = {
    match: FilterMatch;
    filters: Omit<Filter, 'id'>[];
    explanation: string;
};

export type TableInsights = {
    summary: string;
    columns: { name: string; meaning: string }[];
    anomalies: {
        column: string | null;
        issue: string;
        severity: 'info' | 'warning';
    }[];
    questions: string[];
};

export type IndexSuggestions = {
    indexes: {
        statement: string;
        columns: string[];
        reason: string;
        impact: 'high' | 'medium' | 'low';
    }[];
    note: string;
    alerts: {
        code: string;
        severity: 'danger' | 'warn';
        table: string | null;
        columns: string[];
        message: string;
    }[];
    query: string;
};

/**
 * A table as the cached schema describes it, unfolded in the list of tables.
 */
export type TableOutline = {
    name: string;
    columns: {
        name: string;
        type: string;
        nullable: boolean;
        default: string | null;
    }[];
    primary_key: string[];
    indexes: TableIndex[];
    foreign_keys: TableForeignKey[];
};

export type DesignColumnType =
    | 'string'
    | 'char'
    | 'text'
    | 'mediumText'
    | 'longText'
    | 'tinyInteger'
    | 'smallInteger'
    | 'mediumInteger'
    | 'integer'
    | 'bigInteger'
    | 'decimal'
    | 'float'
    | 'double'
    | 'boolean'
    | 'date'
    | 'dateTime'
    | 'timestamp'
    | 'time'
    | 'year'
    | 'json'
    | 'jsonb'
    | 'uuid'
    | 'ulid'
    | 'binary'
    | 'enum'
    | 'custom';

export type DefaultKind = 'none' | 'value' | 'current' | 'expression';

/**
 * A column of the designer. `original` is its name in the database, null for a new column; `changed` tells that
 * its definition must be written again.
 */
export type ColumnDesign = {
    original: string | null;
    changed: boolean;
    name: string;
    type: DesignColumnType;
    dbType?: string;
    length: number | null;
    precision: number | null;
    scale: number | null;
    unsigned: boolean;
    nullable: boolean;
    defaultKind: DefaultKind;
    default: string | null;
    autoIncrement: boolean;
    options: string[];
};

export type IndexDesign = {
    original: string | null;
    name: string | null;
    columns: string[];
    unique: boolean;
};

export type ForeignKeyAction =
    | 'cascade'
    | 'restrict'
    | 'set null'
    | 'set default'
    | 'no action';

export type ForeignKeyDesign = {
    original: string | null;
    name: string | null;
    columns: string[];
    referencesTable: string;
    referencesColumns: string[];
    onDelete: ForeignKeyAction | null;
    onUpdate: ForeignKeyAction | null;
};

export type TableDesign = {
    name: string;
    columns: ColumnDesign[];
    primaryKey: string[];
    indexes: IndexDesign[];
    foreignKeys: ForeignKeyDesign[];
};

export type ImportMode = 'transaction' | 'stop' | 'continue';

export type SqlImportReport = {
    statements: number;
    executed: number;
    skipped: number;
    failed: number;
    failures: { index: number; statement: string; message: string }[];
    kept: boolean;
    duration: number;
};

export type CsvImportReport = {
    inserted: number;
    deleted: number;
    duration: number;
};

export type ConsoleResult = {
    statement: string;
    columns: string[] | null;
    rows: CellValue[][];
    truncated: boolean;
    affected: number | null;
    duration: number;
    /**
     * The table whose rows the result shows as they are, so that its cells can be changed: only in the SQL editor.
     */
    source?: { table: string; primaryKey: string[] } | null;
};

/**
 * How the statements of a run of the SQL editor are kept: each one as it runs, all or none, or none at all.
 */
export type TransactionMode = 'auto' | 'atomic' | 'dry_run';

export type ConsoleRun = {
    results: ConsoleResult[];
    error: { index: number; statement: string; message: string } | null;
    /**
     * The end of the transaction of the run, null in auto-commit.
     */
    transaction?: 'committed' | 'rolled_back' | null;
};
