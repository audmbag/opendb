import { create } from 'zustand';
import { devtools, persist } from 'zustand/middleware';
import { createSelectors } from '@/lib/utils';

export type AgentAccessMode = 'read-only' | 'read-write';

export type AgentEffort = 'default' | 'high';

export interface SelectedModel {
    providerId: string;
    modelId: string;
}

interface AgentSettingsState {
    accessMode: AgentAccessMode;
    effort: AgentEffort;
    selectedModel: SelectedModel | null;
    setAccessMode: (accessMode: AgentAccessMode) => void;
    setEffort: (effort: AgentEffort) => void;
    setSelectedModel: (selectedModel: SelectedModel | null) => void;
}

/**
 * The settings of the prompt input, kept outside of it: the input is replaced while a tool waits for approval
 * and between the home and conversation pages, and requests sent without it still need them.
 */
const useAgentSettingsStore = createSelectors(
    create<AgentSettingsState>()(devtools(persist((set) => ({
        accessMode: 'read-only',
        effort: 'default',
        selectedModel: null,
        setAccessMode: (accessMode) => set({ accessMode }),
        setEffort: (effort) => set({ effort }),
        setSelectedModel: (selectedModel) => set({ selectedModel }),
    }), { name: 'agent-settings' }), { name: 'useAgentSettingsStore' }),
));

export { useAgentSettingsStore };
