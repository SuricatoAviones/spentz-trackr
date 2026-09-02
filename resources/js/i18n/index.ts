import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './en.json';
import es from './es.json';

export type TranslationProps = {
    locale?: string;
    translations?: {
        messages?: Record<string, string>;
        admin?: Record<string, string>;
    };
};

export function getLocale(): string {
    return typeof document !== 'undefined'
        ? document.documentElement.lang || i18n.language || 'es'
        : i18n.language || 'es';
}

export function initI18n(props: TranslationProps): void {
    const locale = props.locale ?? 'es';

    if (!i18n.isInitialized) {
        void i18n.use(initReactI18next).init({
            lng: locale,
            fallbackLng: 'en',
            resources: {
                es: { translation: es },
                en: { translation: en },
            },
            interpolation: { escapeValue: false },
            returnNull: false,
        });
    }

    syncI18n(props);
}

export function syncI18n(props: TranslationProps): void {
    const locale = props.locale ?? i18n.language;

    if (locale !== i18n.language) {
        void i18n.changeLanguage(locale);
    }

    const translations = props.translations;
    if (translations?.messages) {
        i18n.addResourceBundle(locale, 'messages', translations.messages, true, true);
    }
    if (translations?.admin) {
        i18n.addResourceBundle(locale, 'admin', translations.admin, true, true);
    }

    if (typeof document !== 'undefined') {
        document.documentElement.lang = locale;
    }
}