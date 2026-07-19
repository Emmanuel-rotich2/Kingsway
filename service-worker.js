/**
 * Kingsway School Management System - Service Worker
 * 
 * Responsibilities:
 * - Cache application shell (static assets)
 * - Offline fallback page
 * - Safe API caching (GET only)
 * - Background sync registration
 * - Push notification support
 * - Cache version management
 * - Old cache cleanup
 */

const CACHE_VERSION = 'v4';
const STATIC_CACHE_NAME = `kingsway-static-${CACHE_VERSION}`;
const API_CACHE_NAME = `kingsway-api-${CACHE_VERSION}`;

// Assets to cache immediately (application shell).
// ONLY include files that actually exist on disk. A single missing asset used to make
// `cache.addAll()` REJECT and abort install, so the service worker never activated and
// Cache Storage / offline / Background Sync / Push were all silently disabled.
// NOTE: bootstrap-icons / font-awesome / Chart.js are loaded from CDN in the PHP shell and
// are NOT precached here (they degrade gracefully to CDN when offline for rich UI only).
const STATIC_ASSETS = [
  // CSS
  './css/school-theme.css',
  './css/dashboards.css',
  './king.css',
  './public/css/public.css',
  './public/vendor/bootstrap/css/bootstrap.min.css',

  // JavaScript (core app shell)
  './js/api.js',
  './js/core/session_manager.js',
  './js/core/service_worker_manager.js',
  './js/utils/storage_manager.js',
  './js/components/ActionButtons.js',
  './js/components/RoleBasedUI.js',
  './js/components/EnhancedRoleBasedUI.js',
  './js/components/DataTable.js',
  './js/components/ModalForm.js',
  './js/components/UIComponents.js',
  './js/components/PageNavigator.js',
  './js/components/PageShell.js',
  './js/utils/print_manager.js',
  './js/sidebar.js',
  './js/main.js',
  './js/index.js',

  // Images
  './images/favicon/favicon-96x96.png',
  './images/favicon/favicon.svg',
  './images/favicon/favicon.ico',
  './images/favicon/apple-touch-icon.png',
  './images/favicon/web-app-manifest-192x192.png',
  './images/favicon/web-app-manifest-512x512.png',

  // Pages
  './offline.html',
  './home.php'
];

// API endpoints that are safe to cache (GET only, reference data).
// These MUST match the real routes the app calls (verified live on /Kingsway/api/...).
// Updated during the academic re-point work: the previous patterns referenced dead
// slugs (subjects, streams-list, terms, years/list) that never resolved, so offline
// caching silently never primed. Now aligned to the actual endpoints.
const CACHEABLE_API_PATTERNS = [
  /\/api\/academic\/classes-list$/,
  /\/api\/academic\/subjects-list$/,
  /\/api\/academic\/terms-list$/,
  /\/api\/academic\/years-list$/,
  /\/api\/academic\/assessments-list$/,
  /\/api\/academic\/performance-overview$/,
  /\/api\/academic\/context$/,          // eager bootstrap call — must not be Network-Only
  /\/api\/staff\/departments\/get$/,
  /\/api\/school-config\/profile$/
];

// API endpoints that should never be cached
const NEVER_CACHE_PATTERNS = [
  /api\/auth\//,           // Authentication endpoints
  /api\/payments\//,       // Payment endpoints
  /api\/payroll\//,        // Payroll endpoints
  /api\/students$/,        // Student lists (use IndexedDB instead)
  /api\/admissions\//,     // Admission queues (use IndexedDB instead)
  /POST/, /PUT/, /PATCH/, /DELETE/  // Mutations
];

/**
 * Install event - cache static assets
 */
self.addEventListener('install', (event) => {
  console.log('[ServiceWorker] Installing version:', CACHE_VERSION);
  
  event.waitUntil(
    caches.open(STATIC_CACHE_NAME)
      .then(async (cache) => {
        console.log('[ServiceWorker] Caching static assets');
        // Cache assets independently so one missing file does not abort the WHOLE install.
        const results = await Promise.allSettled(
          STATIC_ASSETS.map((url) => cache.add(url))
        );
        results.forEach((r, i) => {
          if (r.status === 'rejected') {
            console.warn('[ServiceWorker] Skipped precache (missing):', STATIC_ASSETS[i], r.reason && r.reason.message);
          }
        });
      })
      .then(() => {
        console.log('[ServiceWorker] Static assets cached successfully');
        return self.skipWaiting(); // Activate immediately
      })
      .catch((error) => {
        console.error('[ServiceWorker] Failed to cache static assets:', error);
      })
  );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', (event) => {
  console.log('[ServiceWorker] Activating version:', CACHE_VERSION);
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            // Delete old version caches
            if (cacheName !== STATIC_CACHE_NAME && cacheName !== API_CACHE_NAME) {
              console.log('[ServiceWorker] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('[ServiceWorker] Old caches cleaned up');
        return self.clients.claim(); // Take control immediately
      })
  );
});

/**
 * Fetch event - handle network requests with caching strategies
 */
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Skip cross-origin requests (except our whitelisted CDNs)
  if (url.origin !== self.location.origin && !isWhitelistedOrigin(url.origin)) {
    return;
  }
  
  // JS/CSS must be network-first. A cache-first app shell is fine for images,
  // but stale page controllers break PHP-rendered screens after deployments.
  if (isScriptOrStyle(request.url)) {
    event.respondWith(handleNetworkFirstStatic(request));
  } else if (isStaticAsset(request.url)) {
    // Cache First for static assets
    event.respondWith(handleCacheFirst(request));
  } else if (isCacheableAPI(request.url)) {
    // Stale While Revalidate for safe API endpoints
    event.respondWith(handleStaleWhileRevalidate(request));
  } else if (isNavigationRequest(request)) {
    // Network First for navigation requests
    event.respondWith(handleNavigationRequest(request));
  } else {
    // Network Only for everything else
    event.respondWith(handleNetworkOnly(request));
  }
});

/**
 * Check if origin is whitelisted for caching
 */
function isWhitelistedOrigin(origin) {
  const whitelistedOrigins = [
    'https://code.jquery.com',
    'https://cdnjs.cloudflare.com'
  ];
  return whitelistedOrigins.some(allowed => origin.includes(allowed));
}

/**
 * Check if request is for a static asset
 */
function isStaticAsset(url) {
  return STATIC_ASSETS.some(asset => url.includes(asset)) ||
         url.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$/);
}

function isScriptOrStyle(url) {
  const pathname = new URL(url).pathname;
  return pathname.endsWith('.js') || pathname.endsWith('.css');
}

/**
 * Check if request is for a cacheable API endpoint
 */
function isCacheableAPI(url) {
  // Check if it's an API request
  if (!url.includes('/api/')) {
    return false;
  }
  
  // Check if it matches cacheable patterns
  if (CACHEABLE_API_PATTERNS.some(pattern => pattern.test(url))) {
    // Make sure it doesn't match never-cache patterns
    return !NEVER_CACHE_PATTERNS.some(pattern => pattern.test(url));
  }
  return false;
}

/**
 * Check if request is a navigation request (HTML page)
 */
function isNavigationRequest(request) {
  return request.mode === 'navigate';
}

/**
 * Cache First strategy - serves from cache, falls back to network
 */
async function handleCacheFirst(request) {
  try {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      console.log('[ServiceWorker] Cache First hit:', request.url);
      return cachedResponse;
    }
    
    console.log('[ServiceWorker] Cache First miss, fetching:', request.url);
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      const cache = await caches.open(STATIC_CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.error('[ServiceWorker] Cache First failed:', error);
    return new Response('Offline', { status: 503 });
  }
}

async function handleNetworkFirstStatic(request) {
  const cache = await caches.open(STATIC_CACHE_NAME);

  try {
    console.log('[ServiceWorker] Network First static:', request.url);
    const networkResponse = await fetch(request, { cache: 'no-cache' });

    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }

    return networkResponse;
  } catch (error) {
    console.warn('[ServiceWorker] Network First static failed, trying cache:', error);
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }

    return new Response('Offline', { status: 503 });
  }
}

/**
 * Stale While Revalidate strategy - serves from cache, updates in background
 */
async function handleStaleWhileRevalidate(request) {
  const cache = await caches.open(API_CACHE_NAME);
  const cachedResponse = await cache.match(request);
  
  // Always fetch from network to update cache
  const networkPromise = fetch(request)
    .then((networkResponse) => {
      if (networkResponse.ok) {
        cache.put(request, networkResponse.clone());
      }
      return networkResponse;
    })
    .catch((error) => {
      console.warn('[ServiceWorker] Network fetch failed, using cache:', error);
      return null;
    });
  
  // Return cached response immediately if available
  if (cachedResponse) {
    console.log('[ServiceWorker] Stale While Revalidate hit:', request.url);
    // Update cache in background
    networkPromise.catch(() => {}); // Don't await
    return cachedResponse;
  }
  
  // If no cache, wait for network
  console.log('[ServiceWorker] Stale While Revalidate miss, fetching:', request.url);
  return networkPromise.then(response => {
    if (response) {
      return response;
    }
    return new Response('Offline', { status: 503 });
  });
}

/**
 * Network First strategy - tries network first, falls back to cache
 */
async function handleNavigationRequest(request) {
  try {
    console.log('[ServiceWorker] Network First for navigation:', request.url);
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful responses
      const cache = await caches.open(STATIC_CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.warn('[ServiceWorker] Network First failed, trying cache:', error);
    
    // Try to serve from cache
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Serve offline page as last resort
    return caches.match('/offline.html');
  }
}

/**
 * Network Only strategy - always fetches from network
 */
async function handleNetworkOnly(request) {
  try {
    console.log('[ServiceWorker] Network Only:', request.url);
    return await fetch(request);
  } catch (error) {
    console.error('[ServiceWorker] Network Only failed:', error);
    return new Response('Offline', { status: 503 });
  }
}

/**
 * Message event - handle messages from clients
 */
self.addEventListener('message', (event) => {
  const { type, data } = event.data;
  
  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;
    
    case 'CACHE_URLS':
      event.waitUntil(cacheUrls(data.urls));
      break;
    
    case 'CLEAR_CACHE':
      event.waitUntil(clearCache(data.cacheName));
      break;
    
    case 'GET_CACHE_STATS':
      event.waitUntil(getCacheStats().then(stats => {
        event.ports[0].postMessage({ type: 'CACHE_STATS', data: stats });
      }));
      break;
    
    default:
      console.warn('[ServiceWorker] Unknown message type:', type);
  }
});

/**
 * Cache specific URLs
 */
async function cacheUrls(urls) {
  const cache = await caches.open(STATIC_CACHE_NAME);
  await cache.addAll(urls);
  console.log('[ServiceWorker] Cached URLs:', urls);
}

/**
 * Clear specific cache
 */
async function clearCache(cacheName) {
  await caches.delete(cacheName);
  console.log('[ServiceWorker] Cleared cache:', cacheName);
}

/**
 * Get cache statistics
 */
async function getCacheStats() {
  const stats = {};
  
  for (const cacheName of [STATIC_CACHE_NAME, API_CACHE_NAME]) {
    try {
      const cache = await caches.open(cacheName);
      const keys = await cache.keys();
      let totalSize = 0;
      
      for (const request of keys) {
        const response = await cache.match(request);
        if (response) {
          const blob = await response.blob();
          totalSize += blob.size;
        }
      }
      
      stats[cacheName] = {
        entries: keys.length,
        totalSize: totalSize
      };
    } catch (error) {
      stats[cacheName] = { error: error.message };
    }
  }
  
  return stats;
}

/**
 * Background sync event (if supported)
 */
self.addEventListener('sync', (event) => {
  console.log('[ServiceWorker] Background sync:', event.tag);
  
  switch (event.tag) {
    case 'sync-outbox':
      event.waitUntil(syncOutbox());
      break;
    
    case 'sync-drafts':
      event.waitUntil(syncDrafts());
      break;
    
    default:
      console.warn('[ServiceWorker] Unknown sync tag:', event.tag);
  }
});

/**
 * Sync offline operations queue
 */
async function syncOutbox() {
  try {
    // This will be implemented when we have the offline queue
    console.log('[ServiceWorker] Syncing outbox...');
    // TODO: Implement actual sync logic
  } catch (error) {
    console.error('[ServiceWorker] Outbox sync failed:', error);
  }
}

/**
 * Sync offline drafts
 */
async function syncDrafts() {
  try {
    // This will be implemented when we have offline drafts
    console.log('[ServiceWorker] Syncing drafts...');
    // TODO: Implement actual sync logic
  } catch (error) {
    console.error('[ServiceWorker] Draft sync failed:', error);
  }
}

/**
 * Push event (if supported)
 */
self.addEventListener('push', (event) => {
  console.log('[ServiceWorker] Push received');
  
  const options = {
    body: event.data ? event.data.text() : 'New notification',
    icon: '/images/favicon/favicon-96x96.png',
    badge: '/images/favicon/favicon-96x96.png',
    vibrate: [200, 100, 200],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'View',
        icon: '/images/favicon/favicon-96x96.png'
      },
      {
        action: 'close',
        title: 'Close',
        icon: '/images/favicon/favicon-96x96.png'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification('Kingsway Academy', options)
  );
});

/**
 * Notification click event
 */
self.addEventListener('notificationclick', (event) => {
  console.log('[ServiceWorker] Notification clicked:', event.notification);
  
  event.notification.close();
  
  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('/')
    );
  }
});

console.log('[ServiceWorker] Service Worker loaded, version:', CACHE_VERSION);
