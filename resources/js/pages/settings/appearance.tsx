import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { LanguagePreference } from '@/components/language-preference';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('settings.appearance_title')} />

            <h1 className="sr-only">{t('settings.appearance_title')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('settings.appearance_title')}
                    description={t('settings.appearance_description')}
                />
                <AppearanceTabs />
                <LanguagePreference />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Settings',
            href: editAppearance(),
        },
    ],
};
