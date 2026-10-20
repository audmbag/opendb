import type { Project } from '@/types/models/project';
import type { ProjectInfo } from '@/hooks/use-project';
import type { DriverConfig } from '@/types/databases';

export type Sensitivity = 'low' | 'medium' | 'high' | 'critical';


export type AvailableStrategy = 'keep' | 'fake' | 'hash' | 'mask' | 'null' | 'static' | 'remapping';

export interface ForeignKeyRef {
    table: string;
    column: string;
    selfReferential: boolean;
}


export type ColumnTransformation =
    | { strategy: 'keep' }
    | { strategy: 'null' }
    | { strategy: 'static'; value: unknown }
    | { strategy: 'fake'; fakerMethod: string; fakerArguments: unknown[] }
    | { strategy: 'hash'; algorithm: string }
    | { strategy: 'mask'; visibleChars: number; maskChar: string; preserveFormat: boolean }
    | { strategy: 'remapping'; remappingUse: string | null; foreignKeys: ForeignKeyRef[] };

export interface DetectedMatcher {
    key: string;
    group: string;
    groupLabel: string;
    name: string;
    sensitivity: Sensitivity;
    matchedPattern: string;
    suggestedTransformation: ColumnTransformation;
}

export type FakerParameterType = 'integer' | 'float' | 'string' | 'boolean' | 'enum' | 'date-expression';

export interface FakerParameter {
    name: string;
    type: FakerParameterType;
    default: unknown;
    nullable: boolean;
    editable: boolean;
    min: number | null;
    max: number | null;
    options: string[] | null;
}


export interface FakerMethodOption {
    fakerMethod: string;
    fakerArguments: unknown[];
    group: string;
    parameters: FakerParameter[];
}


export type FakerCatalog = FakerMethodOption[];

interface CloningColumnBase {
    name: string;
    type: string | null;
    detectedMatcher: DetectedMatcher | null;
    options: AvailableStrategy[];
    fakerCatalogKey: string | null;
    suggestedFakerMethod: FakerMethodOption | null;
}

export interface CloningColumnKeep extends CloningColumnBase {
    strategy: 'keep';
}

export interface CloningColumnNull extends CloningColumnBase {
    strategy: 'null';
}

export interface CloningColumnStatic extends CloningColumnBase {
    strategy: 'static';
    value: unknown;
}

export interface CloningColumnFake extends CloningColumnBase {
    strategy: 'fake';
    fakerMethod: string;
    fakerArguments: unknown[];
}

export interface CloningColumnHash extends CloningColumnBase {
    strategy: 'hash';
    algorithm: string;
}

export interface CloningColumnMask extends CloningColumnBase {
    strategy: 'mask';
    visibleChars: number;
    maskChar: string;
    preserveFormat: boolean;
}

export interface CloningColumnRemapping extends CloningColumnBase {
    strategy: 'remapping';
    remappingUse: string | null;
    foreignKeys: ForeignKeyRef[];
}

export type CloningColumn =
    | CloningColumnKeep
    | CloningColumnNull
    | CloningColumnStatic
    | CloningColumnFake
    | CloningColumnHash
    | CloningColumnMask
    | CloningColumnRemapping;

export type CloningColumnStrategy = CloningColumn['strategy'];

export interface CloningTable {
    name: string;
    rowsStrategy: string;
    rowsClear: string | null;
    columns: CloningColumn[];
    /** Raison pour laquelle la table sera ignorée au clonage, ou null si elle est copiée. */
    skipReason: string | null;
    /** Raison pour laquelle les identifiants de la table seront conservés au lieu d'être remappés, ou null. */
    remappingNotice: string | null;
}

export interface CloningLabels {
    strategies: Record<string, string>;
    fakerMethods: Record<string, string>;
    fakerParameters: Record<string, string>;
    fakerParameterOptions: Record<string, string>;
}

export interface CloningClientPayload {
    fakerCatalogs: Record<string, FakerCatalog>;
    tables: CloningTable[];
    labels?: CloningLabels;
    cloningId: string
}

export interface CloningDriverConfigOptions {
    create_database_if_not_exists?: boolean;
    from_id?: number;
}


export type CloningDriverConfig = Pick<
    DriverConfig,
    'host' | 'port' | 'database' | 'username' | 'password' | 'driver_id'
> &
    CloningDriverConfigOptions;



export type CloningDraft = {
    cloningId: string;
    tables: Record<string, Record<string, ColumnTransformation>>;
};

export type CloningConfigurationData = {
    enforce_column_types: boolean;
    drop_unknown_tables: boolean;
    drop_extra_columns: boolean;
    disable_fk_checks: boolean;
    break_on_failure: boolean;
};

/**
 * Données prêtes à être envoyées au serveur pour lancer le clonage.
 */
export type CloningPayload = {
    cloningDriverConfig: CloningDriverConfig;
    anonymisation: CloningDraft;
    options: CloningConfigurationData;
    agentDisabled: string[];
    cloningDisabled: string[];
};
