import { create } from "zustand"
import type { Provider } from '@/types/models/provider';


interface ProviderState {
    providers: Provider[];
    setProviders: (providers: Provider[]) => void;
}

const providerStore = create<ProviderState>(() => ({
    providers: [],
    setProviders: (providers) => {
        providerStore.setState({ providers });
    },
}));

export { providerStore };
