const SHELL = '/offline.html';
const CACHE = 'sand-offline-v1';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.add(SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.mode !== 'navigate') {
    return;
  }
  if (url.pathname.startsWith('/api') || url.pathname.startsWith('/core')) {
    return;
  }
  event.respondWith(
    fetch(event.request).catch(async () => {
      const cached = await caches.match(SHELL);
      return cached || Response.error();
    }),
  );
});
