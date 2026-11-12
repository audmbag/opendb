import React from 'react';
import AlertDialog from '@/components/alert-dialog';
import AppLogo from '@/components/app-logo';
import CommandModal from '@/components/modals/command-modal';
import SelectFileModal from '@/components/modals/select-file-modal';
import SettingsDialog from '@/components/modals/settings-modal';
import SocialButtons from '@/components/social-buttons';
import Toaster from '@/components/toaster';
import { CommandModalProvider } from '@/hooks/use-command-modal';
import { ProjectModalProvider } from '@/hooks/use-project-modal';
import { SettingsProvider } from '@/hooks/use-settings-modal';


export default function BlankLayout({ children }: { children: React.ReactNode }) {
    return (
        <SettingsProvider>
            <ProjectModalProvider>
            <CommandModalProvider>
                <div className="flex h-screen w-full flex-col p-4">
                    <main className="flex flex-1 flex-col">
                        <div className="flex flex-1 flex-col items-center justify-center gap-4">
                            <AppLogo icon />
                            {children}
                        </div>
                    </main>
                    <footer className="pt-4">
                        <SocialButtons />
                    </footer>
                </div>
                <CommandModal />
                <SettingsDialog />
                <Toaster duration={5000} />
                <AlertDialog />
                <SelectFileModal />
            </CommandModalProvider>
            </ProjectModalProvider>
        </SettingsProvider>
    );
}
