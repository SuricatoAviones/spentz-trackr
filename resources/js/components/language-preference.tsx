import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from '@/components/language-switcher';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export function LanguagePreference() {
    const { t } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    {t('settings.language_title')}
                </CardTitle>
                <CardDescription>
                    {t('settings.language_description')}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <LanguageSwitcher />
            </CardContent>
        </Card>
    );
}
