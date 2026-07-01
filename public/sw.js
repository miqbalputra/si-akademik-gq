const CACHE_NAME = 'nilai-gq-v1';
const STATIC_ASSETS = [
    '/',
    '/login',
    '/manifest.json',
    '/offline.html',
];

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

    // Skip Filament admin panel and API requests
    const url = new URL(request.url);
    if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/livewire')) {
        return;
    }

    // Network-first for navigation requests (pages)
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
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