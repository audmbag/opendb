import {
    BrainCircuitIcon,
    DatabaseIcon,
    FolderIcon,
    NotebookPenIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import React, {
    createContext,
    lazy,
    Suspense,
    useCallback,
    useContext,
    useState,
} from 'react';
import type {
    SmallSidebarNavItem,
    SmallSidebarNavItemValue,
} from '@/components/sm-side-bar/types';

const GeneralProjectSettings = lazy(
    () => import('../components/project-settings/general-project-settings'),
);
const DatabaseProjectSettings = lazy(
    () => import('../components/project-settings/database-project-settings'),
);
const AgentTablesProjectSettings = lazy(
    () =>
        import('../components/project-settings/agent-tables-project-settings'),
);
const MemoryProjectSettings = lazy(
    () => import('../components/project-settings/memory-project-settings'),
);
const DangerZoneProjectSettings = lazy(
    () => import('../components/project-settings/danger-zone-project-settings'),
);

const lazyPage = (page: React.ReactNode) => (
    <Suspense fallback="Chargement...">{page}</Suspense>
);

export const ProjectSettingsNavItems: SmallSidebarNavItem[] = [
    {
        value: 'general',
        name: 'Général',
        icon: FolderIcon,
        component: lazyPage(<GeneralProjectSettings />),
        group: 'Projet',
    },
    {
        value: 'database',
        name: 'Base de données',
        icon: DatabaseIcon,
        component: lazyPage(<DatabaseProjectSettings />),
        group: 'Projet',
    },
    {
        value: 'agent-tables',
        name: 'Accès aux tables',
        icon: BrainCircuitIcon,
        component: lazyPage(<AgentTablesProjectSettings />),
        group: 'Agent IA',
    },
    {
        value: 'memory',
        name: 'Mémoire',
        icon: NotebookPenIcon,
        component: lazyPage(<MemoryProjectSettings />),
        group: 'Agent IA',
    },
    {
        value: 'danger-zone',
        name: 'Zone de danger',
        icon: TriangleAlertIcon,
        component: lazyPage(<DangerZoneProjectSettings />),
        group: 'Avancé',
    },
];

type ProjectSettingsContextType = {
    isOpen: boolean;
    targetTab: SmallSidebarNavItemValue | undefined;
    open: (targetTab?: SmallSidebarNavItemValue) => void;
    close: () => void;
};

const ProjectSettingsContext = createContext<ProjectSettingsContextType>({
    isOpen: false,
    targetTab: undefined,
    open: () => {},
    close: () => {},
});

/**
 * Opens the settings of the current project from anywhere in its pages, on a given section if needed.
 */
const ProjectSettingsProvider = ({
    children,
}: {
    children: React.ReactNode;
}) => {
    const [isOpen, setIsOpen] = useState(false);
    const [targetTab, setTargetTab] = useState<
        SmallSidebarNavItemValue | undefined
    >(undefined);

    const open = useCallback((tab?: SmallSidebarNavItemValue) => {
        setTargetTab(tab);
        setIsOpen(true);
    }, []);

    const close = useCallback(() => setIsOpen(false), []);

    return (
        <ProjectSettingsContext value={{ isOpen, targetTab, open, close }}>
            {children}
        </ProjectSettingsContext>
    );
};

const useProjectSettingsDialog = () => useContext(ProjectSettingsContext);

export { ProjectSettingsProvider, useProjectSettingsDialog };
