const CACHE_NAME = 'metrix-universal-v1';
const ASSETS = [
    '/super-persona',
    '/manifest.json',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS);
        })
    );
});

self.addEventListener('fetch', event => {
    // Solo manejar peticiones GET para el cache
    if (event.request.method === 'GET') {
        event.respondWith(
            caches.match(event.request).then(response => {
                return response || fetch(event.request);
            })
        );
    }
});
