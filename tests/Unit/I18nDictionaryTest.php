<?php

function flatKeys(array $dictionary, string $prefix = ''): array
{
    $keys = [];

    foreach ($dictionary as $key => $value) {
        $fullKey = $prefix === '' ? $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = array_merge($keys, flatKeys($value, $fullKey));
        } else {
            $keys[] = $fullKey;
        }
    }

    return $keys;
}

function loadDictionary(string $locale): array
{
    $path = dirname(__DIR__, 2)."/resources/js/i18n/{$locale}.json";

    return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

function staticTranslationKeys(): array
{
    $root = dirname(__DIR__, 2).'/resources/js';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    $keys = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'ts' && $file->getExtension() !== 'tsx') {
            continue;
        }

        $relative = str_replace('\\', '/', $file->getPathname());

        if (str_contains($relative, '/routes/') || str_contains($relative, '/wayfinder')) {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        preg_match_all('/\bt\(([\'"])([\w:.-]+)\1/', $content, $matches);

        $keys = array_merge($keys, $matches[2]);
    }

    return array_values(array_unique($keys));
}

/**
 * Static prefixes of template-literal keys, e.g. `t(`admin.rates.source_${x}`)`
 * yields "admin.rates.source_". Only the prefix is verifiable, so these are not
 * checked for existence — but they must still be free of the ":" separator,
 * which is how a broken key hid from every check once.
 *
 * @return list<string>
 */
function templateTranslationKeyPrefixes(): array
{
    $root = dirname(__DIR__, 2).'/resources/js';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    $prefixes = [];

    foreach ($iterator as $file) {
        if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
            continue;
        }

        preg_match_all(
            '/\bt\(`([\w:.-]+)(?:\$\{|`)/',
            file_get_contents($file->getPathname()),
            $matches,
        );

        $prefixes = array_merge($prefixes, $matches[1]);
    }

    return array_values(array_unique($prefixes));
}

function resolveKeyVariants(array $dictionaryKeys, string $key): bool
{
    if (in_array($key, $dictionaryKeys, true)) {
        return true;
    }

    foreach (['_one', '_other', '_zero', '_few', '_many', '_two'] as $suffix) {
        if (in_array($key.$suffix, $dictionaryKeys, true)) {
            return true;
        }
    }

    return false;
}

test('es and en dictionaries have identical key sets', function () {
    $esKeys = flatKeys(loadDictionary('es'));
    $enKeys = flatKeys(loadDictionary('en'));

    expect(array_diff($esKeys, $enKeys))->toBe([])
        ->and(array_diff($enKeys, $esKeys))->toBe([]);
});

test('every static translation key used in the frontend exists in the dictionaries', function () {
    $es = loadDictionary('es');

    $knownKeys = flatKeys($es);

    $missing = [];

    foreach (staticTranslationKeys() as $key) {
        if (! resolveKeyVariants($knownKeys, $key)) {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe([]);
});

test('no frontend translation key uses the i18next namespace separator', function () {
    // i18next reads ":" as a namespace separator, so t('admin:users.title')
    // looks for key "users.title" inside a namespace called "admin" — not for
    // "admin.users.title" in the dictionary. The app only registers the default
    // "translation" namespace from es/en.json (plus the backend-provided
    // "messages"/"admin" bundles, which hold different keys), so a colon here
    // renders the raw key in the UI. This regressed the whole admin panel once;
    // keep every key dotted.
    $namespaced = array_values(array_filter(
        array_merge(staticTranslationKeys(), templateTranslationKeyPrefixes()),
        fn (string $key): bool => str_contains($key, ':'),
    ));

    expect($namespaced)->toBe([]);
});

test('interpolated placeholders match between languages', function () {
    $es = flatKeys(loadDictionary('es'));
    $en = flatKeys(loadDictionary('en'));

    $mismatches = [];

    foreach ($es as $key) {
        $esPlaceholders = extractPlaceholders(loadDictionary('es'), $key);
        $enPlaceholders = extractPlaceholders(loadDictionary('en'), $key);

        if ($esPlaceholders !== $enPlaceholders) {
            $mismatches[] = [$key, $esPlaceholders, $enPlaceholders];
        }
    }

    expect($mismatches)->toBe([]);
});

function extractPlaceholders(array $dictionary, string $key): array
{
    $segments = explode('.', $key);
    $value = $dictionary;

    foreach ($segments as $segment) {
        $value = $value[$segment] ?? null;

        if ($value === null) {
            return [];
        }
    }

    // The frontend dictionaries interpolate the i18next way ({{name}}), not the
    // Laravel way (:name) — matching on ":" found nothing and made this check a
    // no-op.
    preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', (string) $value, $matches);

    sort($matches[1]);

    return $matches[1];
}
