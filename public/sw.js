const CACHE_NAME = 'gq-edu-static-v3';
const STATIC_ASSETS = [
    '/',
    '/login',
    '/manifest.json',
    '/offline.html',
    '/icons/icon-192.svg',
    '/icons/icon-512.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-192-maskable.png',
    '/icons/icon-512-maskable.png',
];

const PUBLIC_NAVIGATIONS = new Set(['/', '/login']);

// Install: cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .catch(() => {})
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            ))
    );
    self.clients.claim();
});

// Fetch: network-first for pages, cache-first for static assets
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip Filament admin panel, Livewire, and API requests. In particular,
    // never put authenticated responses or POST-like application flows in a
    // cache that can be reused by another browser session.
    const url = new URL(request.url);
    if (url.origin !== self.location.origin
        || url.pathname.startsWith('/admin')
        || url.pathname.startsWith('/livewire')
        || url.pathname.startsWith('/api')) {
        return;
    }

    // Only public landing/login pages may use the pre-cached navigation shell.
    // Authenticated portal pages are network-only; offline mode must not serve
    // stale/private teacher or guardian data.
    if (request.mode === 'navigate') {
        if (!PUBLIC_NAVIGATIONS.has(url.pathname)) {
            event.respondWith(
                fetch(request).catch(() => caches.match('/offline.html')),
            );
            return;
        }

        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok && response.type === 'basic') {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match('/offline.html')))
        );
        return;
    }

    // Cache-first for static assets (CSS, JS, images, fonts)
    if (request.destination === 'style' || request.destination === 'script' || request.destination === 'image' || request.destination === 'font') {
        event.respondWith(
            caches.match(request)
                .then((cached) => cached || fetch(request).then((response) => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    return response;
                }))
        );
        return;
    }
});
