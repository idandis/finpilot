const CACHE_NAME = 'finpilot-static-v3';
const STATIC_PATH_PREFIXES = ['/build/', '/icons/'];

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// Only ever cache immutable, hashed build assets (JS/CSS/fonts) and icons -
// never Inertia page responses or any request carrying account/transaction
// data, so the app can never serve stale financial data from the cache.
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (!STATIC_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(event.request);

            if (cached) {
                return cached;
            }

            const response = await fetch(event.request);

            if (response.ok) {
                cache.put(event.request, response.clone());
            }

            return response;
        }),
    );
});
