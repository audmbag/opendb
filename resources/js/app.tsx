import { createInertiaApp } from '@inertiajs/react';
import AlertDialog from '@/components/alert-dialog';
import CommandModal from '@/components/command-modal';
import SelectFileModal from '@/components/select-file-modal';
import SettingsDialog from '@/components/settings-modal';
import Toaster from '@/components/toaster';
import { TooltipProvider } from "@/components/ui/tooltip"
import { initializeTheme } from '@/hooks/use-apparence';
import { CommandModalProvider } from '@/hooks/use-command-modal';
import { SettingsProvider } from '@/hooks/use-settings-modal';
import AppLayout from '@/layouts/app';
import BlankLayout from '@/layouts/blank-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        if (['home'].includes(name)) {
            return BlankLayout;
        }

        if (['auth/login'].includes(name)) {
            return null
        }

        return AppLayout;
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <SettingsProvider>
                    <CommandModalProvider>
                        {app}
                        <SelectFileModal />
                        <CommandModal />
                        <SettingsDialog />
                        <AlertDialog />
                        <Toaster duration={5000} />
                    </CommandModalProvider>
                </SettingsProvider>
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});



initializeTheme();
