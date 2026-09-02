import { createInertiaApp } from '@inertiajs/react';
import { useEffect } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initI18n, syncI18n, type TranslationProps } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import TrackerLayout from '@/layouts/tracker-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function I18nBridge({ props }: { props: TranslationProps }) {
    useEffect(() => {
        syncI18n(props);
    }, [props.locale, props.translations]);

    return null;
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name === 'dashboard' ||
                name.startsWith('expenses/') ||
                name.startsWith('incomes/') ||
                name.startsWith('categories/') ||
                name.startsWith('sources/') ||
                name.startsWith('reports/') ||
                name === 'ajustes':
                return TrackerLayout;
            case name.startsWith('admin/'):
                return AppLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app, { page }) {
        initI18n(page.props);

        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <I18nBridge props={page.props} />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Register the service worker for installable PWA support in production...
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Ignore registration failures (offline, unsupported browser, etc.)
        });
    });
}
