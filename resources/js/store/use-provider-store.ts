import { create } from 'zustand';
import { devtools } from 'zustand/middleware';
import { createSelectors } from '@/lib/utils';
import type { Provider } from '@/types/models/provider';

interface ProviderState {
    providers: Provider[];
    setProviders: (providers: Provider[]) => void;
}

const useProviderStore = createSelectors(
    create<ProviderState>()(devtools((set) => ({
        providers: [],
        setProviders: (providers) => set({ providers: providers }),
    }), { name: 'useProviderStore' }),
));

export { useProviderStore };
