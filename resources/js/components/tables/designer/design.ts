import type {
    ColumnDesign,
    DesignColumnType,
    ForeignKeyDesign,
    IndexDesign,
    TableDesign,
} from '../types';

/**
 * A row of the designer: the column with a local id, what it was when the designer opened, and whether the user
 * removed it. A removed column already in the database is kept in the list so that it can be restored.
 */
export type DesignerColumn = ColumnDesign & {
    id: string;
    initial: ColumnDesign | null;
    removed: boolean;
};

export type DesignerIndex = IndexDesign & { id: string };

export type DesignerForeignKey = ForeignKeyDesign & { id: string };

export type TypeOption = {
    value: DesignColumnType;
    label: string;
    engines?: string[];
};

/**
 * The types of a column by group, the label of a group being a key of lang/{locale}/tables.php.
 */
export const TYPE_GROUPS: { label: string; types: TypeOption[] }[] = [
    {
        label: 'tables.designer.type_groups.text',
        types: [
            { value: 'string', label: 'varchar' },
            { value: 'char', label: 'char' },
            { value: 'text', label: 'text' },
            { value: 'mediumText', label: 'mediumtext' },
            { value: 'longText', label: 'longtext' },
        ],
    },
    {
        label: 'tables.designer.type_groups.numbers',
        types: [
            { value: 'tinyInteger', label: 'tinyint' },
            { value: 'smallInteger', label: 'smallint' },
            { value: 'mediumInteger', label: 'mediumint' },
            { value: 'integer', label: 'integer' },
            { value: 'bigInteger', label: 'bigint' },
            { value: 'decimal', label: 'decimal' },
            { value: 'float', label: 'float' },
            { value: 'double', label: 'double' },
            { value: 'boolean', label: 'boolean' },
        ],
    },
    {
        label: 'tables.designer.type_groups.dates',
        types: [
            { value: 'date', label: 'date' },
            { value: 'dateTime', label: 'datetime' },
            { value: 'timestamp', label: 'timestamp' },
            { value: 'time', label: 'time' },
            { value: 'year', label: 'year' },
        ],
    },
    {
        label: 'tables.designer.type_groups.others',
        types: [
            { value: 'json', label: 'json' },
            { value: 'jsonb', label: 'jsonb', engines: ['pgsql'] },
            { value: 'uuid', label: 'uuid' },
            { value: 'ulid', label: 'ulid' },
            { value: 'binary', label: 'binary (blob)' },
            { value: 'enum', label: 'enum' },
        ],
    },
];

export const INTEGER_TYPES: DesignColumnType[] = [
    'tinyInteger',
    'smallInteger',
    'mediumInteger',
    'integer',
    'bigInteger',
];

export const NUMERIC_TYPES: DesignColumnType[] = [
    ...INTEGER_TYPES,
    'decimal',
    'float',
    'double',
];

export const TEMPORAL_TYPES: DesignColumnType[] = ['dateTime', 'timestamp'];

export const hasLength = (type: DesignColumnType) =>
    type === 'string' || type === 'char';

export const hasPrecision = (type: DesignColumnType) =>
    type === 'decimal' || type === 'float';

let sequence = 0;

export const localId = (): string => `local-${++sequence}`;

export function newColumn(
    overrides: Partial<ColumnDesign> = {},
): DesignerColumn {
    return {
        id: localId(),
        initial: null,
        removed: false,
        original: null,
        changed: false,
        name: '',
        type: 'string',
        length: 255,
        precision: null,
        scale: null,
        unsigned: false,
        nullable: true,
        defaultKind: 'none',
        default: null,
        autoIncrement: false,
        options: [],
        ...overrides,
    };
}

export function identifierColumn(): DesignerColumn {
    return newColumn({
        name: 'id',
        type: 'bigInteger',
        length: null,
        unsigned: true,
        nullable: false,
        autoIncrement: true,
    });
}

/**
 * The fields that define a column, compared to tell whether it must be written again.
 */
const DEFINITION_FIELDS: (keyof ColumnDesign)[] = [
    'type',
    'length',
    'precision',
    'scale',
    'unsigned',
    'nullable',
    'defaultKind',
    'default',
    'autoIncrement',
    'options',
];

/**
 * Whether two columns are defined differently, whatever their names.
 */
export const definitionDiffers = (
    column: ColumnDesign,
    other: ColumnDesign,
): boolean =>
    DEFINITION_FIELDS.some(
        (field) =>
            JSON.stringify(column[field]) !== JSON.stringify(other[field]),
    );

export const definitionChanged = (column: DesignerColumn): boolean =>
    column.initial === null || definitionDiffers(column, column.initial);

export function fromDesign(design: TableDesign): {
    columns: DesignerColumn[];
    indexes: DesignerIndex[];
    foreignKeys: DesignerForeignKey[];
} {
    return {
        columns: design.columns.map((column) => ({
            ...column,
            id: localId(),
            initial: { ...column },
            removed: false,
        })),
        indexes: design.indexes.map((index) => ({ ...index, id: localId() })),
        foreignKeys: design.foreignKeys.map((foreignKey) => ({
            ...foreignKey,
            id: localId(),
        })),
    };
}

/**
 * The new table proposed by the assistant, as rows of the designer: none of its elements is in the database yet.
 */
export function fromProposal(design: TableDesign): {
    columns: DesignerColumn[];
    indexes: DesignerIndex[];
    foreignKeys: DesignerForeignKey[];
} {
    return {
        columns: design.columns.map((column) => ({
            ...column,
            original: null,
            changed: false,
            id: localId(),
            initial: null,
            removed: false,
        })),
        indexes: design.indexes.map((index) => ({
            ...index,
            original: null,
            id: localId(),
        })),
        foreignKeys: design.foreignKeys.map((foreignKey) => ({
            ...foreignKey,
            original: null,
            id: localId(),
        })),
    };
}

/**
 * The design sent to the server, with the position of each element so that its errors find their way back.
 */
export function toDesign(
    name: string,
    columns: DesignerColumn[],
    primaryKey: string[],
    indexes: DesignerIndex[],
    foreignKeys: DesignerForeignKey[],
): {
    design: TableDesign;
    columnIds: string[];
    indexIds: string[];
    foreignKeyIds: string[];
} {
    const kept = columns.filter((column) => !column.removed);
    const names = new Set(kept.map((column) => column.name));
    const keptIndexes = indexes.filter((index) =>
        index.columns.every((column) => names.has(column)),
    );
    const keptForeignKeys = foreignKeys.filter((foreignKey) =>
        foreignKey.columns.every((column) => names.has(column)),
    );

    return {
        design: {
            name,
            columns: kept.map((column) => ({
                original: column.original,
                changed: column.original !== null && definitionChanged(column),
                name: column.name,
                type: column.type,
                length: column.length,
                precision: column.precision,
                scale: column.scale,
                unsigned: column.unsigned,
                nullable: column.nullable,
                defaultKind: column.defaultKind,
                default: column.default,
                autoIncrement: column.autoIncrement,
                options: column.options,
            })),
            primaryKey: primaryKey.filter((column) => names.has(column)),
            indexes: keptIndexes.map((index) => ({
                original: index.original,
                name: index.name,
                columns: index.columns,
                unique: index.unique,
            })),
            foreignKeys: keptForeignKeys.map((foreignKey) => ({
                original: foreignKey.original,
                name: foreignKey.name,
                columns: foreignKey.columns,
                referencesTable: foreignKey.referencesTable,
                referencesColumns: foreignKey.referencesColumns,
                onDelete: foreignKey.onDelete,
                onUpdate: foreignKey.onUpdate,
            })),
        },
        columnIds: kept.map((column) => column.id),
        indexIds: keptIndexes.map((index) => index.id),
        foreignKeyIds: keptForeignKeys.map((foreignKey) => foreignKey.id),
    };
}

/**
 * The errors of the server, keyed like `columns.2.name`, moved onto the local id of the element they are about.
 */
export function locateErrors(
    errors: Record<string, string[]>,
    ids: { columns: string[]; indexes: string[]; foreignKeys: string[] },
): Record<string, string> {
    const located: Record<string, string> = {};

    for (const [key, messages] of Object.entries(errors)) {
        const match = /^(columns|indexes|foreignKeys)\.(\d+)(?:\.(\w+))?/.exec(
            key,
        );

        if (match) {
            const id = ids[match[1] as keyof typeof ids][Number(match[2])];
            located[`${id}:${match[3] ?? ''}`] = messages[0];
        } else {
            located[key] = messages[0];
        }
    }

    return located;
}
