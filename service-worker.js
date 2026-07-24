/** Kingsway service worker: safe static caching only. */
const CACHE_VERSION = 'v8.5-safe-bootstrap';
const STATIC_CACHE = `kingsway-static-${CACHE_VERSION}`;
const OFFLINE_URL = './offline.html';
const PRECACHE = [
  './offline.html',
  './css/school-theme.css',
  './css/dashboards.css',
  './king.css',
  './public/vendor/bootstrap/css/bootstrap.min.css',
  './images/favicon/favicon-96x96.png',
  './images/favicon/favicon.svg',
  './images/favicon/favicon.ico'
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(STATIC_CACHE);
    await Promise.allSettled(PRECACHE.map((url) => cache.add(url)));
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter((name) => name.startsWith('kingsway-') && name !== STATIC_CACHE)
      .map((name) => caches.delete(name)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Never intercept API/auth/session requests or mutations.
  if (request.method !== 'GET' || url.pathname.includes('/api/')) return;
  if (url.origin !== self.location.origin) return;

  // Never cache PHP/application navigations. Use network and offline fallback only.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request, { cache: 'no-store' }).catch(async () => {
      return (await caches.match(OFFLINE_URL)) || new Response('Offline', { status: 503 });
    }));
    return;
  }

  // JS and CSS are network-first so deployments cannot execute stale controllers.
  if (/\.(?:js|css)$/i.test(url.pathname)) {
    event.respondWith(fetch(request, { cache: 'no-store' }).catch(async () => {
      return (await caches.match(request)) || new Response('Offline', { status: 503 });
    }));
    return;
  }

  // Cache-first only for immutable visual/font assets.
  if (/\.(?:png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot)$/i.test(url.pathname)) {
    event.respondWith((async () => {
      const cached = await caches.match(request);
      if (cached) return cached;
      const response = await fetch(request);
      if (response.ok) {
        const cache = await caches.open(STATIC_CACHE);
        await cache.put(request, response.clone());
      }
      return response;
    })());
  }
});

self.addEventListener('message', (event) => {
  const type = event.data?.type;
  if (type === 'SKIP_WAITING') self.skipWaiting();
  if (type === 'CLEAR_CACHE') {
    event.waitUntil(event.data?.data?.cacheName
      ? caches.delete(event.data.data.cacheName)
      : Promise.all(caches.keys().then((names) => names.map((name) => caches.delete(name)))));
  }
  if (type === 'GET_CACHE_STATS' && event.ports?.[0]) {
    event.waitUntil((async () => {
      const stats = {};
      for (const name of await caches.keys()) {
        stats[name] = { entries: (await (await caches.open(name)).keys()).length };
      }
      event.ports[0].postMessage({ type: 'CACHE_STATS', data: stats });
    })());
  }
});

self.addEventListener('push', (event) => {
  const data = (() => { try { return event.data?.json() || {}; } catch { return { body: event.data?.text() }; } })();
  event.waitUntil(self.registration.showNotification(data.title || 'Kingsway Academy', {
    body: data.body || 'New notification',
    icon: './images/favicon/favicon-96x96.png',
    badge: './images/favicon/favicon-96x96.png',
    data: { url: data.url || './home.php' }
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(self.clients.openWindow(event.notification.data?.url || './home.php'));
});
