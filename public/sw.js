// Bump this whenever the caching strategy changes: `activate` deletes every
// cache that isn't in CURRENT_CACHES, so a bump also purges stale bundles left
// by older service workers.
const VERSION = 'v2';
const SHELL_CACHE = `spent-trackr-shell-${VERSION}`;
const ASSETS_CACHE = `spent-trackr-assets-${VERSION}`;

const CURRENT_CACHES = [SHELL_CACHE, ASSETS_CACHE];

// Only static, user-agnostic files. Never the app shell ("/"): it embeds
// <script src> tags pointing at content-hashed bundles, so a stale copy boots
// the browser into a build whose assets no longer exist. Nor the manifest: it
// is served per-locale, so caching it cache-first would pin one language.
const SHELL_FILES = [
    '/favicon.ico',
    '/favicon.svg',
    '/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            // Don't let one missing icon abort the whole install.
            .then((cache) => Promise.allSettled(SHELL_FILES.map((file) => cache.add(file)))),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => !CURRENT_CACHES.includes(key))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

/** Content-hashed Vite output — immutable, so cache-first is safe. */
function isImmutableAsset(url) {
    return url.pathname.startsWith('/build/');
}

function isStaticShellFile(url) {
    return SHELL_FILES.includes(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || !request.url.startsWith(self.location.origin)) {
        return;
    }

    const url = new URL(request.url);

    // Everything else — navigations, Inertia XHR, /api, and any authenticated
    // page — goes straight to the network and is NEVER cached. These responses
    // are per-user financial data: caching them would leak one account's data to
    // the next person using the browser and would survive logout.
    if (!isImmutableAsset(url) && !isStaticShellFile(url)) {
        return;
    }

    const cacheName = isImmutableAsset(url) ? ASSETS_CACHE : SHELL_CACHE;

    event.respondWith(
        caches.open(cacheName).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);

            // clone() must happen before the body is consumed by the caller —
            // doing it inside a later .then() throws "Response body is already
            // used" and the put silently never lands.
            if (response.ok && response.status === 200) {
                await cache.put(request, response.clone());
            }

            return response;
        }),
    );
});
