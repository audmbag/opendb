import { usePage } from '@inertiajs/react';
import React from 'react';
import { AppSidebar } from "@/components/app-sidebar";
import { DatabaseStatusBar } from '@/components/database-status-bar';
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import type { Project } from '@/types/models/project';



export default function AppLayout({ children }: { children: React.ReactNode }) {

    const page = usePage<{ project: Project }>();

    return (
        <SidebarProvider defaultOpen={false}>
            <AppSidebar />
            <SidebarInset className="h-screen gap-2 overflow-hidden py-2 pr-2 pl-2 md:pl-0">
                <DatabaseStatusBar
                    project={page.props.project}
                />
                <main className="flex flex-1 flex-col overflow-auto">
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}
