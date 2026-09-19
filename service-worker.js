/** Kingsway service worker: safe static caching only. */
const CACHE_VERSION = 'v10.5-realtime-backoff-recovery';
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
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter((name) => name.startsWith('kingsway-') && name !== STATIC_CACHE)
      .map((name) => caches.delete(name)));
    await self.clients.claim();
    // The worker's in-memory buffer list died with the previous instance
    // (browsers terminate idle SWs after ~30s). Ask whichever client is open
    // to re-send its registered URLs; realtime_manager answers REGISTER_BUFFERS,
    // which re-arms the poll loop. If no client answers, polling resumes on
    // the next page load's registration — same as pre-g8 behavior.
    if (!self.__kingswayBuffers?.length) {
      const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
      for (const client of clients) {
        client.postMessage({ type: 'REQUEST_BUFFERS' });
      }
    }
  })());
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Never intercept API/auth/session requests or mutations.
  if (request.method !== 'GET' || url.pathname.includes('/api/')) return;
  // Never intercept upload or asset paths — must load fresh from server.
  // The app lives under a subdirectory (e.g. /Kingsway/), so url.pathname
  // is /Kingsway/uploads/..., not /uploads/... — use includes() not startsWith().
  if (url.pathname.includes('/uploads/') || url.pathname.includes('/uploads_backup/') || url.pathname.includes('/assets/')) return;
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
    event.respondWith((async () => {
      try {
        const response = await fetch(request, { cache: 'no-store' });
        return response;
      } catch (_) {
        return (await caches.match(request)) || new Response('Offline', { status: 503 });
      }
    })());
    return;
  }

  // Real-time event buffers are strictly network-first and never cached. The
  // buffers are already governed by no-store headers; this guards the fetch
  // path on the client too so a poll can never be answered from a stale cache.
  if (/\/buffers\/.+\.json$/i.test(url.pathname)) {
    event.respondWith(fetch(request, { cache: 'no-store' }).catch(
      () => new Response('{}', { status: 503, headers: { 'Content-Type': 'application/json' } })
    ));
    return;
  }

  // Cache-first only for immutable visual/font assets.
  if (/\.(?:png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot)$/i.test(url.pathname)) {
    event.respondWith((async () => {
      try {
        const cached = await caches.match(request);
        if (cached) return cached;
        const response = await fetch(request);
        if (response.ok) {
          const cache = await caches.open(STATIC_CACHE);
          await cache.put(request, response.clone());
        }
        return response;
      } catch (_) {
        return new Response('', { status: 503 });
      }
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

  // Role-scoped real-time buffers to start polling (from realtime_manager).
  // Payloads never arrive here — only signed static-buffer URLs. The service
  // worker re-polls them on a jittered 12–18s cycle WITHOUT touching PHP and forwards only
  // actual changes to controlled clients.
  if (type === 'REGISTER_BUFFERS' && Array.isArray(event.data?.urls)) {
    self.__kingswayBuffers = event.data.urls
      .filter((u) => typeof u === 'string' && u.startsWith('http'))
      .map((u) => new URL(u, self.location.origin).href);
    self.__kingswayBufferState = {};
    self.__kingswayFailedTicks = 0; // new URLs: reset the backoff cycle
    if (self.__kingswayBuffers.length && !self.__kingswayPollTimer) {
      const schedulePoll = async () => {
        const outcome = await pollRealTimeBuffers();
        let delay = 12000 + Math.floor(Math.random() * 6001);
        if (outcome === 'all_failed' || outcome === 'rotated') {
          // Every buffer failed or 404'd: back off exponentially (30s→5min
          // cap) so a dead origin or purged epoch is not hammered by every
          // browser at once. Keep trying though — a 404 epoch rotation
          // self-heals as soon as the client re-runs the authenticated
          // handshake and re-registers current URLs.
          self.__kingswayFailedTicks = (self.__kingswayFailedTicks || 0) + 1;
          delay = Math.min(300000, 30000 * Math.pow(2, self.__kingswayFailedTicks - 1));
        } else {
          // Any (even partial) success resets the backoff: one dead buffer
          // must not slow polling of the healthy ones.
          self.__kingswayFailedTicks = 0;
        }
        self.__kingswayPollTimer = setTimeout(schedulePoll, delay);
      };
      schedulePoll();
    }
  }
});

// Poll role-scoped static buffers and forward only diffs to clients. Uses
// fetch(no-store) plus the no-store server headers, so each poll returns the
// freshest buffer the web server has written (zero PHP). Clients receive a
// change-detected event with the current scope's payloads.
//
// Returns an outcome for the scheduler's backoff decision:
//   'ok'        — at least one buffer answered (partial failures tolerated)
//   'all_failed'— every fetch threw (network drop / origin down)
//   'rotated'   — every buffer answered but with 404 (daily slug rotation
//                 crossed midnight, or the 48h purge removed this epoch). The
//                 worker cannot mint new URLs itself — only the client's
//                 authenticated handshake can — so it asks clients to
//                 re-register, then backs off until they do.
async function pollRealTimeBuffers() {
  const buffers = self.__kingswayBuffers || [];
  if (!buffers.length) return 'ok';
  const results = [];
  let successes = 0;
  let notFound = 0;
  await Promise.all(buffers.map(async (href) => {
    try {
      const res = await fetch(href, { cache: 'no-store' });
      if (res.status === 404) {
        notFound += 1;
        return;
      }
      if (!res.ok) return;
      const body = await res.text();
      let payload;
      try { payload = JSON.parse(body); } catch { return; }
      successes += 1;
      const key = href;
      const previous = self.__kingswayBufferState?.[key];
      const signature = body; // raw text diff, not a security boundary
      if (previous !== signature) {
        self.__kingswayBufferState = self.__kingswayBufferState || {};
        self.__kingswayBufferState[key] = signature;
        results.push({ url: href, type: 'UPDATE', payload });
      } else {
        results.push({ url: href, type: 'NO_CHANGE' });
      }
    } catch (ignored) {
      /* transient network error: counted as a failure for backoff */
    }
  }));

  if (results.length) {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of clients) {
      client.postMessage({ type: 'BUFFER_POLL', data: results });
    }
  }

  if (notFound > 0 && successes === 0) {
    // Rotation/purge confirmed: tell controlled clients to re-handshake. The
    // realtime manager answers with fresh REGISTER_BUFFERS, which resets
    // __kingswayBufferState and this backoff cycle.
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of clients) {
      client.postMessage({ type: 'BUFFER_ROTATED' });
    }
    return 'rotated';
  }
  return successes > 0 ? 'ok' : 'all_failed';
}

self.addEventListener('push', (event) => {
  const data = (() => { try { return event.data?.json() || {}; } catch { return { body: event.data?.text() }; } })();
  event.waitUntil(self.registration.showNotification(data.title || 'Kingsway Preparatory School', {
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
