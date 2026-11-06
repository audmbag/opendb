import { usePage } from '@inertiajs/react';
import React from 'react';
import AlertDialog from '@/components/alert-dialog';
import { AppSidebar } from "@/components/app-sidebar";
import { DatabaseStatusBar } from '@/components/database-status-bar';
import CommandModal from '@/components/modals/command-modal';
import ProjectSettingsDialog from '@/components/modals/project-settings-modal';
import SelectFileModal from '@/components/modals/select-file-modal';
import SettingsDialog from '@/components/modals/settings-modal';
import Toaster from '@/components/toaster';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { CommandModalProvider } from '@/hooks/use-command-modal';
import { ProjectSettingsProvider } from '@/hooks/use-project-settings-modal';
import { SettingsProvider } from '@/hooks/use-settings-modal';
import type { Project } from '@/types/models/project';



export default function AppLayout({ children }: { children: React.ReactNode }) {

    const { props:  { project } } = usePage<{ project: Project }>();

    return (
        <SettingsProvider>
            <ProjectSettingsProvider>
            <CommandModalProvider>
                <SidebarProvider defaultOpen={false}>
                    <AppSidebar />
                    <SidebarInset className="h-screen gap-2 overflow-hidden py-2 pr-2 pl-2 md:pl-0">
                        <div className="flex items-center gap-2">
                            <SidebarTrigger className="flex md:hidden" />
                            {project.driver_config && (
                                <DatabaseStatusBar
                                    driver_config={project.driver_config}
                                    path={project.path}
                                    projectId={project.id}
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
                <Toaster duration={5000} />
            </CommandModalProvider>
            </ProjectSettingsProvider>
        </SettingsProvider>
    );
}
