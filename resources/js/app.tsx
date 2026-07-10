import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/app';
import { TooltipProvider } from "@/components/ui/tooltip"
import { Toaster } from 'sonner';
import { initializeTheme } from './hooks/user-apparence';
import BlankLayout from '@/layouts/blank-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        if(name === 'home') {
            return BlankLayout;
        }
        
        return AppLayout;
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});



initializeTheme();