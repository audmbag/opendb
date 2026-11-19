import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import React from 'react';
import AlertDialog from '@/components/alert-dialog';
import { AppSidebar } from "@/components/app-sidebar";
import { DatabaseStatusBar } from '@/components/database-status-bar';
import DefaultPasswordNotice from '@/components/default-password-notice';
import CommandModal from '@/components/modals/command-modal';
import ProjectSettingsDialog from '@/components/modals/project-settings-modal';
import SelectFileModal from '@/components/modals/select-file-modal';
import SettingsDialog from '@/components/modals/settings-modal';
import ParametersDialog from '@/components/tables/console/parameters-dialog';
import Toaster from '@/components/toaster';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { CommandModalProvider } from '@/hooks/use-command-modal';
import { usePrivileges } from '@/hooks/use-privileges';
import { useProjectDisconnect } from '@/hooks/use-project-disconnect';
import { ProjectSettingsProvider } from '@/hooks/use-project-settings-modal';
import { SettingsProvider } from '@/hooks/use-settings-modal';
import type { Project } from '@/types/models/project';



export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { t } = useLaravelReactI18n();

    const { props:  { project } } = usePage<{ project: Project }>();
    // Testing the connection needs its password, and opening the folder changes the machine: both are for who manages the project.
    const canManageProject = usePrivileges().canOnProject('manage_project');
    const { disconnect, processing: disconnecting } = useProjectDisconnect(project);

    return (
        <SettingsProvider>
            <ProjectSettingsProvider>
            <CommandModalProvider>
                <SidebarProvider defaultOpen={false}>
                    <AppSidebar />
                    <SidebarInset className="h-screen gap-2 overflow-hidden py-2 pr-2 pl-2 md:pl-0">
                        <div className="flex items-center gap-2">
                            <SidebarTrigger className="flex md:hidden" aria-label={t('common.toggle_sidebar')} />
                            {project.driver_config && (
                                <DatabaseStatusBar
                                    driver_config={project.driver_config}
                                    path={project.path}
                                    projectId={project.id}
                                    buttons={canManageProject ? ['open-folder', 'reconnect', 'copy', 'disconnect'] : ['copy', 'disconnect']}
                                    onDisconnect={disconnect}
                                    disconnecting={disconnecting}
                                    className="ml-0.5"
                                />
                            )}
                        </div>
                        <main className="flex flex-1 flex-col overflow-auto">
                            {children}
                        </main>
                    </SidebarInset>
                </SidebarProvider>
                <SelectFileModal />
                <CommandModal />
                <SettingsDialog />
                <ProjectSettingsDialog />
                <AlertDialog />
                <ParametersDialog />
                <Toaster duration={5000} />
                <DefaultPasswordNotice />
            </CommandModalProvider>
            </ProjectSettingsProvider>
        </SettingsProvider>
    );
}
