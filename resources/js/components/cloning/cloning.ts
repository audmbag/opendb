export type Sensitivity = 'low' | 'medium' | 'high' | 'critical';

export type AvailableStrategy =
    | 'keep'
    | 'fake'
    | 'hash'
    | 'mask'
    | 'null'
    | 'static'
    | 'remapping';

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

interface CloningColumnBase {
    name: string;
    type: string | null;
    detectedMatcher: DetectedMatcher | null;
    options: AvailableStrategy[];
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
}
