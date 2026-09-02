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
        [$namespace, $dottedKey] = array_pad(explode(':', $key, 2), 2, null);

        $resolves = $dottedKey === null
            ? resolveKeyVariants($knownKeys, $key)
            : ($namespace === 'admin'
                ? resolveKeyVariants($knownKeys, "admin.{$dottedKey}")
                : resolveKeyVariants($knownKeys, "{$namespace}.{$dottedKey}"));

        if (! $resolves) {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe([]);
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

    preg_match_all('/:([a-z_]+)/', (string) $value, $matches);

    sort($matches[1]);

    return $matches[1];
}
