const CACHE_NAME = 'sastohub-v1';

const urlsToCache = [
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/manifest.json'
];

// Install event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(urlsToCache);
    })
  );
});

// Fetch event (DO NOT CACHE PHP or HTML)
self.addEventListener('fetch', event => {
  const req = event.request;
  const url = req.url;

  // Skip PHP and HTML files (dynamic pages)
  if (url.endsWith('.php') || url.endsWith('.html') || url.includes('/api/')) {
    event.respondWith(fetch(req)); // always fresh
    return;
  }

  // Static files only → cached
  event.respondWith(
    caches.match(req).then(response => {
      return response || fetch(req);
    })
  );
});

// Activate event
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
});
