/*
 * Tribe's service worker. Pages: network first, falling back to the last copy kept on this device,
 * so the calendar and lists can be read with no signal. Static files: cache first.
 * Only plain GET pages are kept; nothing is cached for other origins.
 */
const VERSION = 'tribe-v1';
const STATIC = ['/css/app.css', '/js/app.js', '/js/lists.js', '/icons/icon-192.png', '/offline.html'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'logout') {
        event.waitUntil(caches.delete(VERSION).then(() => caches.open(VERSION)).then((cache) => cache.addAll(STATIC)));
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Keep successful, logged-in pages; never the login pages or redirects.
                    if (response.ok && !response.redirected && !url.pathname.startsWith('/aanmeld') && !url.pathname.startsWith('/uitnodiging')) {
                        const copy = response.clone();
                        caches.open(VERSION).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match('/offline.html'))),
        );
        return;
    }

    if (/^\/(css|js|icons)\//.test(url.pathname)) {
        event.respondWith(
            caches.match(request, { ignoreSearch: true }).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(VERSION).then((cache) => cache.put(request, copy));
                return response;
            })),
        );
    }
});
