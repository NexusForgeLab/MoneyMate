const CACHE_NAME = 'moneymate-v1';
const ASSETS = [
  '/',
  '/assets/style.css',
  '/assets/logo.png',
  '/assets/icon-192.png'
];

// Install Event
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
  );
});

// Fetch Event (Network First, fall back to Cache)
self.addEventListener('fetch', (e) => {
  e.respondWith(
    fetch(e.request).catch(() => caches.match(e.request))
  );
});
