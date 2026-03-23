const CACHE = 'fm26-v1';
const STATIC = [
    '/',
    '/index.php',
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/assets/js/auth.js',
    '/logo_fm26.png',
    '/logo_fm26_clean.png',
    'https://fonts.googleapis.com/icon?family=Material+Icons+Round',
];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE).then(c => c.addAll(STATIC)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    if (e.request.method !== 'GET') return;

    const url = new URL(e.request.url);

    // API calls: network first, no cache
    if (url.pathname.includes('get_api.php') || url.pathname.includes('clear_cache.php')) {
        return;
    }

    // Firebase: network only
    if (url.hostname.includes('firestore.googleapis.com') || url.hostname.includes('firebase')) {
        return;
    }

    e.respondWith(
        caches.match(e.request).then(cached => {
            const network = fetch(e.request).then(res => {
                if (res.ok) {
                    const clone = res.clone();
                    caches.open(CACHE).then(c => c.put(e.request, clone));
                }
                return res;
            });
            return cached || network;
        })
    );
});