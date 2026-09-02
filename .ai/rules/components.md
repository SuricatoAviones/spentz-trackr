---
paths:
  - resources/js/components/language-switcher.tsx
---

# Components

## Language switcher uses Wayfinder POST + i18n keys
The language selector posts to `language.update` via `router.post(updateLanguage().url, { locale }, { preserveScroll: true })`; the I18nBridge in app.tsx syncs i18next on the returned props, so no reload/redirect handling is needed. Locale option labels are endonyms (Español/English, same in both dictionaries) and live under `settings:language_*` keys. Keep `es.json`/`en.json` key-identical.
