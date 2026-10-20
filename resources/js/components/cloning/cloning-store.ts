import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';
import  {
    AvailableStrategy,
    CloningClientPayload,
    CloningColumn, CloningDraft,
    CloningLabels,
    ColumnTransformation,
    DetectedMatcher,
    FakerCatalog,
    FakerMethodOption,
} from './cloning.types';


export type ColumnKey = `${string}.${string}`;

export const EMPTY_LABELS: CloningLabels = {
    strategies: {},
    fakerMethods: {},
    fakerParameters: {},
    fakerParameterOptions: {},
};

export const columnKey = (table: string, column: string): ColumnKey => `${table}.${column}`;

export interface ColumnMeta {
    table: string;
    name: string;
    type: string | null;
    detectedMatcher: DetectedMatcher | null;
    options: AvailableStrategy[];
    fakerCatalogKey: string | null;
    suggestedFakerMethod: FakerMethodOption | null;
}

function defaultTransformationFor(strategy: AvailableStrategy, meta: ColumnMeta | undefined,): ColumnTransformation {
    switch (strategy) {
        case 'keep':
            return { strategy: 'keep' };
        case 'null':
            return { strategy: 'null' };
        case 'static':
            return { strategy: 'static', value: '' };
        case 'hash':
            return { strategy: 'hash', algorithm: 'sha256' };
        case 'mask':
            return {
                strategy: 'mask',
                visibleChars: 4,
                maskChar: '*',
                preserveFormat: true,
            };
        case 'fake': {
            const suggested = meta?.suggestedFakerMethod;

            if (suggested) {
                return {
                    strategy: 'fake',
                    fakerMethod: suggested.fakerMethod,
                    fakerArguments: [...suggested.fakerArguments],
                };
            }

            return { strategy: 'fake', fakerMethod: '', fakerArguments: [] };
        }
        case 'remapping':
            return {
                strategy: 'remapping',
                remappingUse: 'random_integer',
                foreignKeys: [],
            };
    }
}

interface CloningState {
    originalPayload: CloningClientPayload | null;
    cloningId: string | null;
    fakerCatalogs: Record<string, FakerCatalog>;
    labels: CloningLabels;
    meta: Record<ColumnKey, ColumnMeta>;
    tableOrder: string[];
    columnsByTable: Record<string, string[]>;

    initialValues: Record<ColumnKey, ColumnTransformation>;
    values: Record<ColumnKey, ColumnTransformation>;

    loaded: boolean;

    loadPayload: (payload: CloningClientPayload) => void;
    clear: () => void;

    getMeta: (table: string, column: string) => ColumnMeta | undefined;
    getTransformation: (
        table: string,
        column: string,
    ) => ColumnTransformation | undefined;

    getFakerOptions: (table: string, column: string) => FakerMethodOption[];

    setStrategy: (
        table: string,
        column: string,
        strategy: AvailableStrategy,
    ) => void;

    applyFakerMethod: (
        table: string,
        column: string,
        method: FakerMethodOption,
    ) => void;

    setFakerArgument: (
        table: string,
        column: string,
        index: number,
        value: unknown,
    ) => void;

    setField: <K extends string>(
        table: string,
        column: string,
        field: K,
        value: unknown,
    ) => void;

    resetColumn: (table: string, column: string) => void;

    /** Passe toutes les colonnes qui le permettent en stratégie "keep". */
    keepAll: () => void;

    /** Restaure toutes les colonnes à leur état d'origine. */
    resetAll: () => void;

    isColumnDirty: (table: string, column: string) => boolean;

    dirtyKeys: () => ColumnKey[];

    buildDraft: (onlyDirty?: boolean) => CloningDraft;
}

export const useCloningStore = create<CloningState>()(
    immer((set, get) => ({
        originalPayload: null,
        cloningId: null,
        fakerCatalogs: {},
        labels: EMPTY_LABELS,
        meta: {},
        tableOrder: [],
        columnsByTable: {},
        initialValues: {},
        values: {},
        loaded: false,

        loadPayload: (payload) =>
            set((state) => {
                state.originalPayload = payload;
                state.cloningId = payload.cloningId;
                state.fakerCatalogs = payload.fakerCatalogs;
                state.labels = payload.labels ?? EMPTY_LABELS;
                state.meta = {};
                state.initialValues = {};
                state.values = {};
                state.tableOrder = payload.tables.map((t) => t.name);
                state.columnsByTable = {};

                for (const table of payload.tables) {
                    state.columnsByTable[table.name] = table.columns.map(
                        (c) => c.name,
                    );

                    for (const column of table.columns) {
                        const key = columnKey(table.name, column.name);
                        const {
                            strategy,
                            name,
                            type,
                            detectedMatcher,
                            options,
                            fakerCatalogKey,
                            suggestedFakerMethod,
                            ...rest
                        } = column as CloningColumn & Record<string, unknown>;

                        state.meta[key] = {
                            table: table.name,
                            name,
                            type,
                            detectedMatcher,
                            options,
                            fakerCatalogKey,
                            suggestedFakerMethod,
                        };

                        // Reconstruit la transformation courante depuis les champs à plat de la colonne.
                        const transformation = {
                            strategy,
                            ...rest,
                        } as ColumnTransformation;
                        state.initialValues[key] = transformation;
                        state.values[key] = transformation;
                    }
                }

                state.loaded = true;
            }),

        clear: () =>
            set((state) => {
                state.originalPayload = null;
                state.cloningId = null;
                state.fakerCatalogs = {};
                state.labels = EMPTY_LABELS;
                state.meta = {};
                state.tableOrder = [];
                state.columnsByTable = {};
                state.initialValues = {};
                state.values = {};
                state.loaded = false;
            }),

        getMeta: (table, column) => get().meta[columnKey(table, column)],

        getTransformation: (table, column) =>
            get().values[columnKey(table, column)],

        getFakerOptions: (table, column) => {
            const meta = get().meta[columnKey(table, column)];

            if (!meta) {
                return [];
            }

            const catalog = meta.fakerCatalogKey
                ? (get().fakerCatalogs[meta.fakerCatalogKey] ?? [])
                : [];
            const suggested = meta.suggestedFakerMethod;

            if (!suggested) {
                return catalog;
            }

            const rest = catalog.filter(
                (m) => m.fakerMethod !== suggested.fakerMethod,
            );

            return [suggested, ...rest];
        },

        setStrategy: (table, column, strategy) =>
            set((state) => {
                const key = columnKey(table, column);
                const meta = state.meta[key];

                if (!meta || !meta.options.includes(strategy)) {
                    return;
                }

                state.values[key] = defaultTransformationFor(
                    strategy,
                    meta,
                ) as never;
            }),

        applyFakerMethod: (table, column, method) =>
            set((state) => {
                const key = columnKey(table, column);

                if (!state.meta[key]) {
                    return;
                }

                state.values[key] = {
                    strategy: 'fake',
                    fakerMethod: method.fakerMethod,
                    fakerArguments: [...method.fakerArguments],
                } as never;
            }),

        setFakerArgument: (table, column, index, value) =>
            set((state) => {
                const key = columnKey(table, column);
                const current = state.values[key];

                if (!current || current.strategy !== 'fake') {
                    return;
                }

                const args = [...current.fakerArguments];
                args[index] = value;
                (
                    state.values[key] as { fakerArguments: unknown[] }
                ).fakerArguments = args;
            }),

        setField: (table, column, field, value) =>
            set((state) => {
                const key = columnKey(table, column);
                const current = state.values[key];

                if (!current || !(field in current)) {
                    return; // ex. tenter de poser "algorithm" alors que la stratégie active est "keep"
                }

                (state.values[key] as unknown as Record<string, unknown>)[
                    field
                ] = value;
            }),

        resetColumn: (table, column) =>
            set((state) => {
                const key = columnKey(table, column);
                const original = state.initialValues[key];

                if (original) {
                    state.values[key] = original;
                }
            }),

        keepAll: () =>
            set((state) => {
                for (const key of Object.keys(state.meta) as ColumnKey[]) {
                    if (state.meta[key].options.includes('keep')) {
                        state.values[key] = { strategy: 'keep' };
                    }
                }
            }),

        resetAll: () =>
            set((state) => {
                for (const key of Object.keys(
                    state.initialValues,
                ) as ColumnKey[]) {
                    state.values[key] = state.initialValues[key];
                }
            }),

        isColumnDirty: (table, column) => {
            const key = columnKey(table, column);
            const state = get();

            return (
                JSON.stringify(state.values[key]) !==
                JSON.stringify(state.initialValues[key])
            );
        },

        dirtyKeys: () => {
            const state = get();

            return (Object.keys(state.values) as ColumnKey[]).filter(
                (key) =>
                    JSON.stringify(state.values[key]) !==
                    JSON.stringify(state.initialValues[key]),
            );
        },

        buildDraft: (onlyDirty = false): CloningDraft => {
            const state = get();
            const keys = onlyDirty
                ? state.dirtyKeys()
                : (Object.keys(state.values) as ColumnKey[]);

            const draft = {} as CloningDraft['tables'];

            for (const key of keys) {
                const meta = state.meta[key];

                if (!meta) {
                    continue;
                }

                draft[meta.table] ??= {};
                draft[meta.table][meta.name] = state.values[key];
            }

            return {
                cloningId: state.cloningId || '',
                tables: draft,
            };
        },
    })),
);
