const CACHE = 'spent-trackr-v1';
const ASSETS_CACHE = 'spent-trackr-assets-v1';
const DATA_CACHE = 'spent-trackr-data-v1';

const ASSETS_TO_CACHE = [
    '/',
    '/manifest.webmanifest',
    '/favicon.ico',
    '/favicon.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(ASSETS_CACHE).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        }),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== ASSETS_CACHE && key !== DATA_CACHE) {
                        return caches.delete(key);
                    }
                }),
            );
        }),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || !request.url.startsWith(self.location.origin)) {
        return;
    }

    const url = new URL(request.url);

    if (url.pathname.startsWith('/api') || url.pathname.startsWith('/expenses') || url.pathname.startsWith('/categories') || url.pathname.startsWith('/sources')) {
        event.respondWith(
            fetch(request).then((response) => {
                if (response.ok && response.status === 200) {
                    caches.open(DATA_CACHE).then((cache) => {
                        cache.put(request, response.clone());
                    });
                }
                return response;
            }).catch(() => caches.match(request)),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/')),
        );
        return;
    }

    event.respondWith(
        caches.open(ASSETS_CACHE).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const network = fetch(request)
                .then((response) => {
                    if (response.ok && response.status === 200) {
                        cache.put(request, response.clone());
                    }
                    return response;
                })
                .catch(() => cached);

            return network || cached;
        }),
    );
});