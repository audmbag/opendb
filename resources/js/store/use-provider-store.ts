import { create } from 'zustand';
import { createSelectors } from '@/lib/utils';
import type { Provider } from '@/types/models/provider';

interface ProviderState {
    connectedProviders: Provider[];
    disconnectedProviders: Provider[];
    setProviders: (providers: Provider[], type: 'connected' | 'disconnected') => void;
}

const useProviderStore = createSelectors(create<ProviderState>((set) => ({
    connectedProviders: [],
    disconnectedProviders: [],
    setProviders: (providers, type) => {
        if (type === 'connected') {
            set({ connectedProviders: providers });
        } else {
            set({ disconnectedProviders: providers });
        }
    },
})));

export { useProviderStore };
