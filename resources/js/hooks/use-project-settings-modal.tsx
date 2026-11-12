import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArchiveIcon,
    BrainCircuitIcon,
    DatabaseIcon,
    FolderIcon,
    KeyRoundIcon,
    NotebookPenIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import React, {
    createContext,
    lazy,
    Suspense,
    useCallback,
    useContext,
    useMemo,
    useState,
} from 'react';
import type {
    SmallSidebarNavItem,
    SmallSidebarNavItemValue,
} from '@/components/sm-side-bar/types';
import { usePrivileges } from '@/hooks/use-privileges';

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
const AccessProjectSettings = lazy(
    () => import('../components/project-settings/access-project-settings'),
);
const ArchivesProjectSettings = lazy(
    () => import('../components/project-settings/archives-project-settings'),
);
const DangerZoneProjectSettings = lazy(
    () => import('../components/project-settings/danger-zone-project-settings'),
);

const lazyPage = (page: React.ReactNode) => <Suspense>{page}</Suspense>;

/**
 * The sections of the project settings, their name and group being keys of lang/{locale}/projects.php.
 */
const PROJECT_SETTINGS_NAV_ITEMS: (SmallSidebarNavItem & { access: 'manage' | 'share' | 'view' })[] = [
    {
        value: 'general',
        name: 'projects.settings.nav.general',
        icon: FolderIcon,
        component: lazyPage(<GeneralProjectSettings />),
        group: 'projects.settings.nav.groups.project',
        access: 'manage',
    },
    {
        value: 'database',
        name: 'projects.settings.nav.database',
        icon: DatabaseIcon,
        component: lazyPage(<DatabaseProjectSettings />),
        group: 'projects.settings.nav.groups.project',
        access: 'manage',
    },
    {
        value: 'agent-tables',
        name: 'projects.settings.nav.agent_tables',
        icon: BrainCircuitIcon,
        component: lazyPage(<AgentTablesProjectSettings />),
        group: 'projects.settings.nav.groups.agent',
        access: 'manage',
    },
    {
        value: 'memory',
        name: 'projects.settings.nav.memory',
        icon: NotebookPenIcon,
        component: lazyPage(<MemoryProjectSettings />),
        group: 'projects.settings.nav.groups.agent',
        access: 'manage',
    },
    {
        value: 'archives',
        name: 'projects.settings.nav.archives',
        icon: ArchiveIcon,
        component: lazyPage(<ArchivesProjectSettings />),
        group: 'projects.settings.nav.groups.agent',
        access: 'view',
    },
    {
        value: 'access',
        name: 'projects.settings.nav.access',
        icon: KeyRoundIcon,
        component: lazyPage(<AccessProjectSettings />),
        group: 'projects.settings.nav.groups.project',
        access: 'share',
    },
    {
        value: 'danger-zone',
        name: 'projects.settings.nav.danger_zone',
        icon: TriangleAlertIcon,
        component: lazyPage(<DangerZoneProjectSettings />),
        group: 'projects.settings.nav.groups.advanced',
        access: 'manage',
    },
];

/**
 * The sections of the project settings the user may open, named in the language of the interface.
 */
export function useProjectSettingsNavItems(): SmallSidebarNavItem[] {
    const { t } = useLaravelReactI18n();
    const { canOnProject, canShare } = usePrivileges();
    const canManage = canOnProject('manage_project');

    return useMemo(
        () =>
            PROJECT_SETTINGS_NAV_ITEMS.filter(({ access }) => ({ manage: canManage, share: canShare, view: true })[access]).map((item) => ({
                value: item.value,
                icon: item.icon,
                component: item.component,
                name: t(item.name),
                group: item.group && t(item.group),
            })),
        [t, canManage, canShare],
    );
}

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
