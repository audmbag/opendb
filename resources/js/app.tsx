import { createInertiaApp } from '@inertiajs/react';
import { LaravelReactI18nProvider } from 'laravel-react-i18n';
import { TooltipProvider } from "@/components/ui/tooltip"
import { initializeTheme } from '@/hooks/use-apparence';
import { initializeColorTheme } from '@/hooks/use-color-theme';
import { getLocale, initializeLocale } from '@/hooks/use-locale';
import { initializeSyntaxTheme } from '@/hooks/use-syntax-theme';
import AppLayout from '@/layouts/app';
import BlankLayout from '@/layouts/blank-layout';
import { initializeForbiddenRequestToasts } from '@/lib/http-errors';
import { TranslatorBridge } from '@/lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Both languages are in the bundle, from the translation files of lang/: changing the language is immediate.
 */
const translations = import.meta.glob('/lang/*.json', { eager: true });

initializeLocale();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        if (['home'].includes(name)) {
            return BlankLayout;
        }

        if (['auth/login', 'error'].includes(name)) {
            return null
        }

        return AppLayout;
    },
    strictMode: true,
    withApp(app) {
        return (
            <LaravelReactI18nProvider
                locale={getLocale()}
                fallbackLocale="en"
                files={translations}
            >
                <TranslatorBridge />
                <TooltipProvider delayDuration={0}>{app}</TooltipProvider>
            </LaravelReactI18nProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});



initializeTheme();
initializeColorTheme();
initializeSyntaxTheme();
initializeForbiddenRequestToasts();
