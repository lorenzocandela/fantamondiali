importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyAUlVRrqZg8qL6_eYwSrp0czllt2IHL0eg",
    authDomain: "fantamondiali-e1f5c.firebaseapp.com",
    projectId: "fantamondiali-e1f5c",
    storageBucket: "fantamondiali-e1f5c.firebasestorage.app",
    messagingSenderId: "607003325581",
    appId: "1:607003325581:web:ac19cf13f7e5e401f75ec4"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(payload => {
    const data = payload.data ?? {};
    const title = data.title ?? 'FantaMondiali';
    const options = {
        body: data.body ?? '',
        icon: '/logo_fm26.png',
        badge: '/logo_fm26.png',
        data: { url: data.url ?? '/' },
        vibrate: [100, 50, 100],
    };
    return self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    const url = e.notification.data?.url ?? '/';
    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(wins => {
            const existing = wins.find(w => w.url.includes(url));
            if (existing) return existing.focus();
            return clients.openWindow(url);
        })
    );
});

// cache (mantenuto dal vecchio sw.js)

const CACHE = 'fm26-v2';
const STATIC = [
    '/',
    '/index.php',
    '/logo_fm26.png',
    '/logo_fm26_clean.png',
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
    if (url.pathname.includes('get_api.php') || url.pathname.includes('clear_cache.php') || url.pathname.includes('send_notification.php')) return;
    if (url.hostname.includes('firestore.googleapis.com') || url.hostname.includes('firebase') || url.hostname.includes('fcm')) return;

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