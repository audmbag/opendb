import { createContext, useContext } from 'react';
import { createStore, useStore } from 'zustand';
import type { StoreApi } from 'zustand';
import { devtools } from 'zustand/middleware';
import { immer } from 'zustand/middleware/immer';
import type { TableChange } from '@/components/tables/types';
import type { CellValue, RecordKey } from '@/types/agent';

export type PendingUpdate = {
    key: RecordKey;
    values: Record<string, CellValue>;
};

export type PendingInsert = { id: string; values: Record<string, CellValue> };

/**
 * Where a change sent to the server comes from in the grid, to point at it when the server refuses it.
 */
export type ChangeTarget =
    | { type: 'update' | 'delete'; rowKey: string }
    | { type: 'insert'; id: string };

export interface TableChangesState {
    updates: Record<string, PendingUpdate>;
    inserts: PendingInsert[];
    deletes: Record<string, RecordKey>;
    /**
     * The errors of the last attempt, keyed like `update:{rowKey}`, `update:{rowKey}:{column}` or `insert:{id}:{column}`.
     */
    errors: Record<string, string>;
    setValue: (
        rowKey: string,
        key: RecordKey,
        column: string,
        value: CellValue,
        original: CellValue,
    ) => void;
    addInsert: (values?: Record<string, CellValue>) => string;
    setInsertValue: (id: string, column: string, value: CellValue) => void;
    removeInsert: (id: string) => void;
    toggleDelete: (rowKey: string, key: RecordKey) => void;
    revertRow: (rowKey: string) => void;
    setErrors: (errors: Record<string, string>) => void;
    discard: () => void;
}

/**
 * The identity of a row in the pending changes: its primary key, whatever page shows it.
 */
export const rowKeyOf = (key: RecordKey): string => JSON.stringify(key);

export const errorKey = (target: ChangeTarget, column?: string): string =>
    [target.type, target.type === 'insert' ? target.id : target.rowKey, column]
        .filter(Boolean)
        .join(':');

const empty = { updates: {}, inserts: [], deletes: {}, errors: {} };

export type TableChangesStore = StoreApi<TableChangesState>;

/**
 * The changes the user made in the grid of one table and has not applied yet. Each opened tab has its own store,
 * so switching to another table keeps them until the tab is closed.
 */
export const createTableChangesStore = (table: string): TableChangesStore =>
    createStore<TableChangesState>()(
        devtools(
            immer((set) => ({
                ...structuredClone(empty),
                setValue: (rowKey, key, column, value, original) =>
                    set((state) => {
                        const update = (state.updates[rowKey] ??= {
                            key,
                            values: {},
                        });

                        if (value === original) {
                            delete update.values[column];
                        } else {
                            update.values[column] = value;
                        }

                        if (Object.keys(update.values).length === 0) {
                            delete state.updates[rowKey];
                        }

                        delete state.errors[`update:${rowKey}:${column}`];
                        delete state.errors[`update:${rowKey}`];
                    }),
                addInsert: (values = {}) => {
                    const id = Math.random().toString(36).slice(2, 10);

                    set((state) => {
                        state.inserts.unshift({ id, values });
                    });

                    return id;
                },
                setInsertValue: (id, column, value) =>
                    set((state) => {
                        const insert = state.inserts.find(
                            (item) => item.id === id,
                        );

                        if (insert) {
                            insert.values[column] = value;
                            delete state.errors[`insert:${id}:${column}`];
                            delete state.errors[`insert:${id}`];
                        }
                    }),
                removeInsert: (id) =>
                    set((state) => {
                        state.inserts = state.inserts.filter(
                            (item) => item.id !== id,
                        );
                    }),
                toggleDelete: (rowKey, key) =>
                    set((state) => {
                        if (state.deletes[rowKey]) {
                            delete state.deletes[rowKey];
                        } else {
                            state.deletes[rowKey] = key;
                        }

                        delete state.errors[`delete:${rowKey}`];
                    }),
                revertRow: (rowKey) =>
                    set((state) => {
                        delete state.updates[rowKey];
                        delete state.deletes[rowKey];
                    }),
                setErrors: (errors) => set({ errors }),
                discard: () => set(structuredClone(empty)),
            })),
            { name: `useTableChangesStore:${table}` },
        ),
    );

export const TableChangesContext = createContext<TableChangesStore | null>(
    null,
);

/**
 * The store of the changes of the table of the current tab.
 */
export function useTableChangesApi(): TableChangesStore {
    const store = useContext(TableChangesContext);

    if (!store) {
        throw new Error(
            'useTableChangesStore must be used within a table tab.',
        );
    }

    return store;
}

export function useTableChangesStore<T>(
    selector: (state: TableChangesState) => T,
): T {
    return useStore(useTableChangesApi(), selector);
}

export const pendingChangesCount = (
    state: Pick<TableChangesState, 'updates' | 'inserts' | 'deletes'>,
): number =>
    Object.keys(state.updates).length +
    state.inserts.length +
    Object.keys(state.deletes).length;

/**
 * The changes in the order they are applied, with where each of them comes from. A row both updated and deleted is only deleted.
 */
export function collectChanges(
    state: Pick<TableChangesState, 'updates' | 'inserts' | 'deletes'>,
): { changes: TableChange[]; targets: ChangeTarget[] } {
    const changes: TableChange[] = [];
    const targets: ChangeTarget[] = [];

    for (const [rowKey, update] of Object.entries(state.updates)) {
        if (!state.deletes[rowKey]) {
            changes.push({
                type: 'update',
                key: update.key,
                values: update.values,
            });
            targets.push({ type: 'update', rowKey });
        }
    }

    for (const insert of [...state.inserts].reverse()) {
        changes.push({ type: 'insert', values: insert.values });
        targets.push({ type: 'insert', id: insert.id });
    }

    for (const [rowKey, key] of Object.entries(state.deletes)) {
        changes.push({ type: 'delete', key });
        targets.push({ type: 'delete', rowKey });
    }

    return { changes, targets };
}

/**
 * The errors of the server, keyed like `changes.2.values.email`, moved onto the rows and cells they are about.
 */
export function errorsOnTargets(
    errors: Record<string, string[]>,
    targets: ChangeTarget[],
): Record<string, string> {
    const located: Record<string, string> = {};

    for (const [field, messages] of Object.entries(errors)) {
        const match = /^changes\.(\d+)(?:\.values\.(.+))?$/.exec(field);
        const target = match ? targets[Number(match[1])] : undefined;

        if (target) {
            located[errorKey(target, match?.[2])] = messages[0];
        }
    }

    return located;
}
