const CACHE_NAME = 'admin-mobile-v1';
const STATIC_ASSETS = [
    './app-icon.svg',
    '../assets/css/styles.css',
    '../assets/js/admin-mobile.js'
];
const STATIC_PATH_ENDINGS = [
    '/admin/app-icon.svg',
    '/assets/css/styles.css',
    '/assets/js/admin-mobile.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .catch(() => null)
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.map((key) => {
                if (key !== CACHE_NAME) {
                    return caches.delete(key);
                }
                return null;
            })))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.pathname.endsWith('/admin/mobile-api.php')) {
        return;
    }

    if (STATIC_PATH_ENDINGS.some((path) => url.pathname.endsWith(path))) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request))
        );
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : './mobile.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    if ('navigate' in client) {
                        return client.navigate(url).then((focusedClient) => (focusedClient || client).focus());
                    }
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(url);
            }

            return null;
        })
    );
});
