// La versione fa parte del nome: cambiandola, la routine di `activate` qui
// sotto butta via tutte le cache con un nome diverso. È così che si sgombera
// la roba vecchia dai browser di chi ha già usato l'app.
const CACHE_NAME = 'finpilot-static-v4';

// Solo le icone.
//
// Gli asset di /build/ stavano qui e sono stati tolti: hanno già l'hash del
// contenuto nel nome, quindi il browser li tiene in cache da solo e in modo
// sicuro. Passando dal service worker invece si accumulavano per sempre - la
// pulizia all'activate non scattava mai, perché il nome della cache era una
// costante - e dopo un deploy poteva capitare di servire un chunk vecchio
// accanto a uno nuovo, con la pagina che non si apriva più finché non si
// svuotava la cache a mano.
const STATIC_PATH_PREFIXES = ['/icons/'];

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

// Mai le risposte delle pagine e mai nulla che porti con sé dati di conti o
// movimenti: dalla cache non deve poter uscire un saldo vecchio.
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
