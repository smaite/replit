// Fetch event (DO NOT CACHE PHP, HTML, IMAGES)
self.addEventListener('fetch', event => {
  const req = event.request;
  const url = req.url;

  // Skip all dynamic pages + images
  const skipExtensions = ['.php', '.html', '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.css'];

  if (skipExtensions.some(ext => url.endsWith(ext))) {
    event.respondWith(fetch(req)); // always fresh
    return;
  }

  // Cache static files only (CSS/JS/manifest)
  event.respondWith(
    caches.match(req).then(response => {
      return response || fetch(req);
    })
  );
});
