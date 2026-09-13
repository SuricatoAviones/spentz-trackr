---
paths:
  - resources/js/components/language-switcher.tsx
---

# Components

## Language switcher uses Wayfinder POST + i18n keys
The language selector switches i18next **first** (`applyLocale(code)` from `@/i18n`) and only then posts to `language.update` via `router.post(updateLanguage().url, { locale }, { preserveScroll: true, preserveState: true })`. Both dictionaries ship in the bundle and nothing user-facing is translated server-side, so the UI must flip on click instead of waiting for the round-trip; the I18nBridge in app.tsx reconciles on the returned props (a no-op when they agree). The active option reads `i18n.language`, not `usePage().props.locale` — the prop lags behind by one request. Locale option labels are endonyms (Español/English, same in both dictionaries) and live under `settings:language_*` keys. Keep `es.json`/`en.json` key-identical.
