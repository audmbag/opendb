import { create } from 'zustand';
import { devtools } from 'zustand/middleware';
import type { ProjectInfo } from '@/hooks/use-project';
import { createSelectors } from '@/lib/utils';
import type { DriverConfig } from '@/types/databases';
import type { Project } from '@/types/models/project';


interface ProjectStoreState {
    tmpProject: Partial<Project> | null;
    fill: (project: ProjectInfo) => void;
    setDriverConfig: (config: DriverConfig) => void;
    clear: () => void;
}

const useTempProjectStore = createSelectors(
    create<ProjectStoreState>()(
        devtools(
            (set) => ({
                tmpProject: null,
                fill: (projectInfo: ProjectInfo) => set(state => ({
                    tmpProject: { ...state.tmpProject, ...projectInfo }
                })),
                setDriverConfig: (config: DriverConfig) =>
                    set((state) => {
                        if (!state.tmpProject) {
                            return state;
                        }

                        return {
                            tmpProject: {
                                ...state.tmpProject,
                                driver_config: config,
                            },
                        };
                    }),
                clear: () => set({ tmpProject: null }),
            }),
            { name: 'useTempProjectStore' },
        ),
    ),
);


export { useTempProjectStore }
