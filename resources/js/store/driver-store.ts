import { create } from 'zustand';
import { createSelectors } from '@/lib/utils';
import type { Driver } from "@/types/databases"


interface DriverStoreState {
    drivers: Driver[];
    fillDrivers: (data: Driver[]) => void;
}

const DriverStore = createSelectors(create<DriverStoreState>((set, get) => ({
    drivers: [],
    fillDrivers: (drivers: Driver[]) => set({ drivers })
})))



export { DriverStore }
