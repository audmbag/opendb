import { createContext, useContext } from 'react';
import { createStore, useStore } from 'zustand';
import type { StoreApi } from 'zustand';

export type GridPosition = { row: number; column: number };

export interface TableGridState {
    active: GridPosition | null;
    editing: boolean;
    /**
     * The text typed on a selected cell, which opens its editor and replaces its value.
     */
    initialInput: string | null;
    /**
     * The ids of the rows ticked in the gutter, for the actions on several rows at once.
     */
    selected: Record<string, true>;
    /**
     * The last row ticked or unticked, where a Shift+click range starts.
     */
    anchor: string | null;
    select: (position: GridPosition | null) => void;
    startEditing: (initialInput?: string | null) => void;
    stopEditing: () => void;
    toggleRow: (id: string) => void;
    selectRows: (ids: string[], anchor?: string | null) => void;
    clearSelection: () => void;
}

export type TableGridStore = StoreApi<TableGridState>;

/**
 * The selected cell of the grid of one tab, whether it is being edited, and the ticked rows. Each cell reads only
 * whether it is the active one, so moving the selection renders two cells instead of the whole page.
 */
export const createTableGridStore = (): TableGridStore =>
    createStore<TableGridState>()((set) => ({
        active: null,
        editing: false,
        initialInput: null,
        selected: {},
        anchor: null,
        select: (active) => set({ active, editing: false, initialInput: null }),
        startEditing: (initialInput = null) =>
            set({ editing: true, initialInput }),
        stopEditing: () => set({ editing: false, initialInput: null }),
        toggleRow: (id) =>
            set((state) => {
                const selected = { ...state.selected };

                if (selected[id]) {
                    delete selected[id];
                } else {
                    selected[id] = true;
                }

                return { selected, anchor: id };
            }),
        selectRows: (ids, anchor = null) =>
            set((state) => ({
                selected: {
                    ...state.selected,
                    ...Object.fromEntries(ids.map((id) => [id, true])),
                },
                anchor: anchor ?? state.anchor,
            })),
        clearSelection: () => set({ selected: {}, anchor: null }),
    }));

export const TableGridContext = createContext<TableGridStore | null>(null);

/**
 * The store of the grid of the current tab.
 */
export function useTableGridApi(): TableGridStore {
    const store = useContext(TableGridContext);

    if (!store) {
        throw new Error('useTableGridStore must be used within a table tab.');
    }

    return store;
}

export function useTableGridStore<T>(
    selector: (state: TableGridState) => T,
): T {
    return useStore(useTableGridApi(), selector);
}
