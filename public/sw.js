/*
 * Tribe's service worker. Pages: network first, falling back to the last copy kept on this device,
 * so the calendar and lists can be read with no signal. Static files: cache first.
 * Only plain GET pages are kept; nothing is cached for other origins.
 */
const VERSION = 'tribe-v3';
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
            // Pages ask for /css/app.css?v=<file time>, so a changed file has a new address and is fetched
            // fresh. Only with no signal is an older copy (any ?v=) better than nothing.
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(VERSION).then((cache) => cache.put(request, copy));
                return response;
            }).catch(() => caches.match(request, { ignoreSearch: true }))),
        );
    }
});

// Notifications from Tribe (tomorrow's agenda, a new gathering, who gives the lift). Tapping one opens the page.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch { data = { body: event.data && event.data.text() }; }
    event.waitUntil(self.registration.showNotification(data.title || 'Tribe', {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: { url: data.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const open = windows.find((w) => w.url.startsWith(self.location.origin));
        if (open) return open.navigate(url).then((w) => w && w.focus());
        return self.clients.openWindow(url);
    }));
});
