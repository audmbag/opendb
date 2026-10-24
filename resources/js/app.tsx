import { createInertiaApp } from '@inertiajs/react';
import { TooltipProvider } from "@/components/ui/tooltip"
import { initializeTheme } from '@/hooks/use-apparence';
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
                {app}
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});



initializeTheme();
